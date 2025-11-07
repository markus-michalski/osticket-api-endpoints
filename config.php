<?php

// Only include osTicket classes if they exist (not in test environment)
if (defined('INCLUDE_DIR') && file_exists(INCLUDE_DIR . 'class.plugin.php')) {
    require_once INCLUDE_DIR . 'class.plugin.php';
}

/**
 * API Endpoints Plugin Configuration
 *
 * Configuration options for the API Endpoints plugin, allowing admins
 * to enable/disable individual API endpoints and control plugin behavior.
 */
class ApiEndpointsConfig extends PluginConfig {

    /**
     * Translate strings (for i18n support)
     *
     * @param string $plugin Plugin identifier
     * @return array Translation functions
     */
    static function translate($plugin = 'api-endpoints') {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate($plugin);
    }

    /**
     * Build configuration options
     *
     * @return array Configuration fields
     */
    function getOptions() {
        list($__, $_N) = self::translate();

        return array(
            // Main enable/disable toggle
            'enabled' => new BooleanField(array(
                'id' => 'enabled',
                'label' => $__('Enable API Endpoints'),
                'configuration' => array(
                    'desc' => $__('When enabled, extended API endpoints will be available. Access control is managed via API Key Permissions below.')
                ),
                'default' => true
            )),

            // Section: Available API Endpoints (Info only)
            'section_endpoints' => new SectionBreakField(array(
                'label' => $__('Available API Endpoints'),
            )),

            'endpoints_info' => new FreeTextField(array(
                'id' => 'endpoints_info',
                'label' => '',
                'configuration' => array(
                    'html' => true,
                    'content' => '<div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2196F3; margin: 10px 0;">
                        <p style="margin: 0 0 10px 0;"><strong>The following API endpoints are available:</strong></p>
                        <ul style="margin: 5px 0; padding-left: 20px; list-style: none;">
                            <li style="margin: 5px 0;">✓ <strong>POST /tickets</strong> - Create tickets with format (markdown/html/text) and department<br>
                               <span style="color: #666; font-size: 11px;">Controlled by: <code>can_create_tickets</code> permission</span></li>
                            <li style="margin: 5px 0;">✓ <strong>PATCH/PUT /tickets/:id</strong> - Update tickets (department, topic, parent, priority, status, SLA, staff)<br>
                               <span style="color: #666; font-size: 11px;">Controlled by: <code>can_update_tickets</code> permission</span></li>
                            <li style="margin: 5px 0;">✓ <strong>GET /tickets-get.php/:number</strong> - Retrieve ticket by number with full details and thread<br>
                               <span style="color: #666; font-size: 11px;">Controlled by: <code>can_read_tickets</code> permission</span></li>
                            <li style="margin: 5px 0;">✓ <strong>GET /tickets-search.php</strong> - Search tickets by query, status, department with pagination<br>
                               <span style="color: #666; font-size: 11px;">Controlled by: <code>can_search_tickets</code> permission</span></li>
                            <li style="margin: 5px 0;">✓ <strong>DELETE /tickets-delete.php/:number</strong> - Delete tickets with cascading cleanup<br>
                               <span style="color: #666; font-size: 11px;">Controlled by: <code>can_delete_tickets</code> permission</span></li>
                            <li style="margin: 5px 0;">✓ <strong>GET /tickets-stats.php</strong> - Retrieve ticket statistics for dashboards and reporting<br>
                               <span style="color: #666; font-size: 11px;">Controlled by: <code>can_read_stats</code> permission</span></li>
                            <li style="margin: 5px 0;">✓ <strong>Subticket Operations</strong> - Manage parent-child ticket relationships (get parent, list children, create/unlink)<br>
                               <span style="color: #666; font-size: 11px;">Controlled by: <code>can_manage_subtickets</code> permission (requires Subticket Manager Plugin)</span></li>
                        </ul>
                        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                            <strong>Note:</strong> Access to these endpoints is controlled individually per API Key using the permissions below.
                        </p>
                    </div>'
                )
            )),

            // Section: API Key Permissions
            'section_permissions' => new SectionBreakField(array(
                'label' => $__('API Key Permissions'),
            )),

            'api_key_permissions_table' => new class extends FreeTextField {
                function __construct() {
                    parent::__construct(array(
                        'id' => 'api_key_permissions_table',
                        'label' => '',
                        'configuration' => array(
                            'html' => true,
                            'content' => '' // Will be generated dynamically
                        )
                    ));
                }

                function getConfiguration() {
                    // Generate table HTML dynamically on each render (not cached)
                    $config = parent::getConfiguration();
                    $config['content'] = ApiEndpointsConfig::renderApiKeyPermissionsTable();
                    return $config;
                }
            },

            // Section: Markdown Integration
            'section_markdown' => new SectionBreakField(array(
                'label' => $__('Markdown Integration'),
            )),

            'require_markdown_plugin' => new BooleanField(array(
                'id' => 'require_markdown_plugin',
                'label' => $__('Require Markdown Plugin'),
                'configuration' => array(
                    'desc' => $__('When enabled, API will reject ticket creation with format=markdown if Markdown Support plugin is not active. When disabled, format parameter is still validated but requests proceed as "html" fallback.')
                ),
                'default' => false // Lenient by default
            )),

            // Installed version (for auto-update tracking)
            'installed_version' => new TextboxField(array(
                'id' => 'installed_version',
                'label' => $__('Installed Version'),
                'configuration' => array(
                    'desc' => $__('Currently installed version (automatically updated)'),
                    'size' => 10,
                    'length' => 10
                ),
                'default' => '',
                'required' => false,
                'disabled' => true // Read-only field
            ))
        );
    }

    /**
     * Get list of allowed permission columns for validation
     *
     * @return array List of allowed column names
     */
    private function getAllowedPermissionColumns() {
        return array(
            'can_create_tickets',
            'can_update_tickets',
            'can_read_tickets',
            'can_search_tickets',
            'can_delete_tickets',
            'can_read_stats',
            'can_manage_subtickets'
        );
    }

    /**
     * Check if a column exists in the API key table
     *
     * @param string $columnName Column name to check
     * @return bool True if column exists
     */
    private function columnExists($columnName) {
        return self::columnExistsStatic($columnName);
    }

    /**
     * Static version: Check if a column exists in the API key table
     *
     * @param string $columnName Column name to check
     * @return bool True if column exists
     */
    private static function columnExistsStatic($columnName) {
        // Get allowed columns statically
        $allowedColumns = array(
            'can_create_tickets',
            'can_update_tickets',
            'can_read_tickets',
            'can_search_tickets',
            'can_delete_tickets',
            'can_read_stats',
            'can_manage_subtickets'
        );

        // Validate column name against whitelist
        if (!in_array($columnName, $allowedColumns)) {
            return false;
        }

        // Use parameterized query with escaped column name
        $sql = sprintf(
            "SHOW COLUMNS FROM %s LIKE '%s'",
            API_KEY_TABLE,
            db_real_escape($columnName)
        );
        $result = db_query($sql);
        return ($result && db_num_rows($result) > 0);
    }

    /**
     * Pre-save validation and processing
     *
     * @param array $config Configuration array (by reference)
     * @param array $errors Errors array (by reference)
     * @return bool True if valid
     */
    function pre_save(&$config = [], &$errors = []) {
        // Handle API Key permissions from $_POST (not in $config because FreeTextField doesn't register fields)
        if (isset($_POST['api_key_create_permissions']) || isset($_POST['api_key_update_permissions']) || isset($_POST['api_key_read_permissions']) || isset($_POST['api_key_search_permissions']) || isset($_POST['api_key_delete_permissions']) || isset($_POST['api_key_subticket_permissions'])) {
            // Check which permission columns exist using helper method
            $hasUpdateColumn = $this->columnExists('can_update_tickets');
            $hasReadColumn = $this->columnExists('can_read_tickets');
            $hasSearchColumn = $this->columnExists('can_search_tickets');
            $hasDeleteColumn = $this->columnExists('can_delete_tickets');
            $hasStatsColumn = $this->columnExists('can_read_stats');
            $hasSubticketColumn = $this->columnExists('can_manage_subtickets');

            // Get all API keys
            $result = db_query('SELECT id FROM ' . API_KEY_TABLE);

            $createPermissions = isset($_POST['api_key_create_permissions'])
                ? $_POST['api_key_create_permissions']
                : array();
            $updatePermissions = isset($_POST['api_key_update_permissions'])
                ? $_POST['api_key_update_permissions']
                : array();
            $readPermissions = isset($_POST['api_key_read_permissions'])
                ? $_POST['api_key_read_permissions']
                : array();
            $searchPermissions = isset($_POST['api_key_search_permissions'])
                ? $_POST['api_key_search_permissions']
                : array();
            $deletePermissions = isset($_POST['api_key_delete_permissions'])
                ? $_POST['api_key_delete_permissions']
                : array();
            $statsPermissions = isset($_POST['api_key_stats_permissions'])
                ? $_POST['api_key_stats_permissions']
                : array();
            $subticketPermissions = isset($_POST['api_key_subticket_permissions'])
                ? $_POST['api_key_subticket_permissions']
                : array();

            while ($row = db_fetch_array($result)) {
                $apiKeyId = (int)$row['id'];

                // Check if permissions are set for this API key (checkbox was checked)
                $canCreate = isset($createPermissions[$apiKeyId]) ? 1 : 0;
                $canUpdate = isset($updatePermissions[$apiKeyId]) ? 1 : 0;
                $canRead = isset($readPermissions[$apiKeyId]) ? 1 : 0;
                $canSearch = isset($searchPermissions[$apiKeyId]) ? 1 : 0;
                $canDelete = isset($deletePermissions[$apiKeyId]) ? 1 : 0;
                $canStats = isset($statsPermissions[$apiKeyId]) ? 1 : 0;
                $canSubticket = isset($subticketPermissions[$apiKeyId]) ? 1 : 0;

                // Build UPDATE SQL with validated columns and escaped values
                // Using backticks for column names and casting values to int for SQL safety
                $setClauses = array();

                // Always update can_create_tickets (it should always exist)
                $setClauses[] = sprintf('`can_create_tickets` = %d', (int)$canCreate);

                // Add other columns only if they exist (validated through whitelist)
                if ($hasUpdateColumn) {
                    $setClauses[] = sprintf('`can_update_tickets` = %d', (int)$canUpdate);
                }
                if ($hasReadColumn) {
                    $setClauses[] = sprintf('`can_read_tickets` = %d', (int)$canRead);
                }
                if ($hasSearchColumn) {
                    $setClauses[] = sprintf('`can_search_tickets` = %d', (int)$canSearch);
                }
                if ($hasDeleteColumn) {
                    $setClauses[] = sprintf('`can_delete_tickets` = %d', (int)$canDelete);
                }
                if ($hasStatsColumn) {
                    $setClauses[] = sprintf('`can_read_stats` = %d', (int)$canStats);
                }
                if ($hasSubticketColumn) {
                    $setClauses[] = sprintf('`can_manage_subtickets` = %d', (int)$canSubticket);
                }

                // Execute UPDATE with properly validated and escaped values
                $sql = sprintf(
                    'UPDATE %s SET %s WHERE `id` = %d',
                    API_KEY_TABLE,
                    implode(', ', $setClauses),
                    (int)$apiKeyId
                );
                db_query($sql);
            }
        }

        // Validate that at least one endpoint is enabled if plugin is enabled
        $enabled = isset($config['enabled']) ? $config['enabled'] : $this->get('enabled');
        $endpoint_create = isset($config['endpoint_create_ticket'])
            ? $config['endpoint_create_ticket']
            : $this->get('endpoint_create_ticket');

        if ($enabled && !$endpoint_create) {
            // Check if any future endpoint is enabled
            $has_active_endpoint = false;
            $future_endpoints = ['endpoint_get_ticket', 'endpoint_update_ticket',
                'endpoint_search_tickets', 'endpoint_delete_ticket', 'endpoint_ticket_stats'];

            foreach ($future_endpoints as $ep) {
                $ep_val = isset($config[$ep]) ? $config[$ep] : $this->get($ep);
                if ($ep_val) {
                    $has_active_endpoint = true;
                    break;
                }
            }

            if (!$has_active_endpoint) {
                $errors['endpoint_create_ticket'] = 'At least one endpoint must be enabled';
                return false;
            }
        }

        return true;
    }

    /**
     * Render API Key Permissions Table
     *
     * Displays all API keys with their permissions as checkboxes.
     * The can_update_tickets permission is editable, while can_create_tickets
     * is shown as read-only for reference.
     *
     * @return string HTML table with API key permissions
     */
    static function renderApiKeyPermissionsTable() {
        // Check which permission columns exist using secure helper method
        $hasUpdateColumn = self::columnExistsStatic('can_update_tickets');
        $hasReadColumn = self::columnExistsStatic('can_read_tickets');
        $hasSearchColumn = self::columnExistsStatic('can_search_tickets');
        $hasDeleteColumn = self::columnExistsStatic('can_delete_tickets');
        $hasStatsColumn = self::columnExistsStatic('can_read_stats');
        $hasSubticketColumn = self::columnExistsStatic('can_manage_subtickets');

        // DEBUG: Log column check results
        error_log('DEBUG ApiEndpointsPlugin: hasSubticketColumn = ' . ($hasSubticketColumn ? 'TRUE' : 'FALSE'));

        // Query all API keys (conditionally include columns)
        $columns = 'id, ipaddr, apikey, isactive, can_create_tickets';
        if ($hasUpdateColumn) $columns .= ', can_update_tickets';
        if ($hasReadColumn) $columns .= ', can_read_tickets';
        if ($hasSearchColumn) $columns .= ', can_search_tickets';
        if ($hasDeleteColumn) $columns .= ', can_delete_tickets';
        if ($hasStatsColumn) $columns .= ', can_read_stats';
        if ($hasSubticketColumn) $columns .= ', can_manage_subtickets';
        $columns .= ', created, updated';

        $sql = "SELECT $columns FROM " . API_KEY_TABLE . " ORDER BY created DESC";
        $result = db_query($sql);

        if (!$result || db_num_rows($result) == 0) {
            return '<div class="info-banner" style="padding: 10px; background: #f0f0f0; border-left: 4px solid #2196F3;">
                <strong>No API Keys found.</strong> Create API keys in the <a href="apikeys.php">API Keys</a> section first.
            </div>';
        }

        // No DEBUG banner - plugin detection is too risky in config context

        // Build HTML table with accordion-style permissions
        $html = '<div style="width: 100%; overflow-x: auto;">
        <style>
            .api-permissions-table { width: 100% !important; border-collapse: collapse; margin-top: 10px; table-layout: fixed; min-width: 100%; }
            .api-permissions-table th { background: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; }
            .api-permissions-table td { padding: 10px; border-bottom: 1px solid #eee; }
            .api-permissions-table tr.main-row:hover { background: #fafafa; cursor: pointer; }
            .api-key-truncated { font-family: monospace; font-size: 11px; color: #666; }
            .status-active { color: #4caf50; font-weight: 600; }
            .status-inactive { color: #f44336; font-weight: 600; }
            .toggle-permissions { color: #2196F3; text-decoration: none; font-weight: 600; padding: 4px 12px; border: 1px solid #2196F3; border-radius: 3px; display: inline-block; background: white; }
            .toggle-permissions:hover { background: #2196F3; color: white; }
            .permissions-details { display: none; background: #f9f9f9; }
            .permissions-details.expanded { display: table-row; }
            .permissions-details td { padding: 20px; }
            .permissions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
            .permission-item { display: flex; align-items: center; gap: 8px; padding: 8px; background: white; border-radius: 4px; border: 1px solid #e0e0e0; }
            .permission-item label { cursor: pointer; margin: 0; font-weight: normal; }
            .permission-checkbox { cursor: pointer; margin: 0; }
            .permission-endpoint { font-family: monospace; font-size: 11px; color: #666; display: block; margin-top: 2px; }
        </style>

        <script>
        function togglePermissions(apiKeyId) {
            var detailsRow = document.getElementById("permissions-" + apiKeyId);
            if (detailsRow.classList.contains("expanded")) {
                detailsRow.classList.remove("expanded");
            } else {
                detailsRow.classList.add("expanded");
            }
        }
        </script>';

        $html .= '<table class="api-permissions-table">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>API Key</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>';

        while ($row = db_fetch_array($result)) {
            $apiKeyId = (int)$row['id'];
            $ipAddr = htmlspecialchars($row['ipaddr']);
            $apiKeyFull = htmlspecialchars($row['apikey']);
            $apiKeyTruncated = substr($apiKeyFull, 0, 8) . '...' . substr($apiKeyFull, -8);
            $isActive = (int)$row['isactive'];
            $canCreate = (int)$row['can_create_tickets'];
            $canUpdate = ($hasUpdateColumn && isset($row['can_update_tickets'])) ? (int)$row['can_update_tickets'] : 0;
            $canRead = ($hasReadColumn && isset($row['can_read_tickets'])) ? (int)$row['can_read_tickets'] : 0;
            $canSearch = ($hasSearchColumn && isset($row['can_search_tickets'])) ? (int)$row['can_search_tickets'] : 0;
            $canDelete = ($hasDeleteColumn && isset($row['can_delete_tickets'])) ? (int)$row['can_delete_tickets'] : 0;
            $canStats = ($hasStatsColumn && isset($row['can_read_stats'])) ? (int)$row['can_read_stats'] : 0;
            $canSubticket = ($hasSubticketColumn && isset($row['can_manage_subtickets'])) ? (int)$row['can_manage_subtickets'] : 0;
            $created = htmlspecialchars($row['created']);

            $statusClass = $isActive ? 'status-active' : 'status-inactive';
            $statusText = $isActive ? 'Active' : 'Inactive';

            // Main row
            $html .= '<tr class="main-row" onclick="togglePermissions(' . $apiKeyId . ')">';
            $html .= '<td>' . $ipAddr . '</td>';
            $html .= '<td><span class="api-key-truncated" title="' . $apiKeyFull . '">' . $apiKeyTruncated . '</span></td>';
            $html .= '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
            $html .= '<td>' . $created . '</td>';
            $html .= '<td style="text-align: right;">';
            $html .= '<a href="javascript:void(0);" class="toggle-permissions" onclick="event.stopPropagation(); togglePermissions(' . $apiKeyId . ');">Manage Permissions</a>';
            $html .= '</td>';
            $html .= '</tr>';

            // Permissions details row (initially hidden)
            $html .= '<tr class="permissions-details" id="permissions-' . $apiKeyId . '">';
            $html .= '<td colspan="5">';
            $html .= '<strong style="display: block; margin-bottom: 12px;">API Endpoint Permissions:</strong>';
            $html .= '<div class="permissions-grid">';

            // Permission: Create Tickets
            $html .= '<div class="permission-item">';
            $html .= '<input type="checkbox" name="api_key_create_permissions[' . $apiKeyId . ']" ' .
                     'value="1" class="permission-checkbox" id="create_' . $apiKeyId . '" ' .
                     ($canCreate ? 'checked' : '') . ' />';
            $html .= '<label for="create_' . $apiKeyId . '">';
            $html .= '<strong>Create Tickets</strong>';
            $html .= '<span class="permission-endpoint">POST /tickets</span>';
            $html .= '</label>';
            $html .= '</div>';

            // Permission: Update Tickets (only if column exists)
            if ($hasUpdateColumn) {
                $html .= '<div class="permission-item">';
                $html .= '<input type="checkbox" name="api_key_update_permissions[' . $apiKeyId . ']" ' .
                         'value="1" class="permission-checkbox" id="update_' . $apiKeyId . '" ' .
                         ($canUpdate ? 'checked' : '') . ' />';
                $html .= '<label for="update_' . $apiKeyId . '">';
                $html .= '<strong>Update Tickets</strong>';
                $html .= '<span class="permission-endpoint">PATCH /tickets/:id</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            // Permission: Read Tickets (only if column exists)
            if ($hasReadColumn) {
                $html .= '<div class="permission-item">';
                $html .= '<input type="checkbox" name="api_key_read_permissions[' . $apiKeyId . ']" ' .
                         'value="1" class="permission-checkbox" id="read_' . $apiKeyId . '" ' .
                         ($canRead ? 'checked' : '') . ' />';
                $html .= '<label for="read_' . $apiKeyId . '">';
                $html .= '<strong>Read Tickets</strong>';
                $html .= '<span class="permission-endpoint">GET /tickets/:number</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            // Permission: Search Tickets (only if column exists)
            if ($hasSearchColumn) {
                $html .= '<div class="permission-item">';
                $html .= '<input type="checkbox" name="api_key_search_permissions[' . $apiKeyId . ']" ' .
                         'value="1" class="permission-checkbox" id="search_' . $apiKeyId . '" ' .
                         ($canSearch ? 'checked' : '') . ' />';
                $html .= '<label for="search_' . $apiKeyId . '">';
                $html .= '<strong>Search Tickets</strong>';
                $html .= '<span class="permission-endpoint">GET /tickets/search</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            // Permission: Delete Tickets (only if column exists)
            if ($hasDeleteColumn) {
                $html .= '<div class="permission-item">';
                $html .= '<input type="checkbox" name="api_key_delete_permissions[' . $apiKeyId . ']" ' .
                         'value="1" class="permission-checkbox" id="delete_' . $apiKeyId . '" ' .
                         ($canDelete ? 'checked' : '') . ' />';
                $html .= '<label for="delete_' . $apiKeyId . '">';
                $html .= '<strong>Delete Tickets</strong>';
                $html .= '<span class="permission-endpoint">DELETE /tickets/:number</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            // Permission: Read Stats (only if column exists)
            if ($hasStatsColumn) {
                $html .= '<div class="permission-item">';
                $html .= '<input type="checkbox" name="api_key_stats_permissions[' . $apiKeyId . ']" ' .
                         'value="1" class="permission-checkbox" id="stats_' . $apiKeyId . '" ' .
                         ($canStats ? 'checked' : '') . ' />';
                $html .= '<label for="stats_' . $apiKeyId . '">';
                $html .= '<strong>Read Stats</strong>';
                $html .= '<span class="permission-endpoint">GET /tickets-stats</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            // Permission: Manage Subtickets (only if column exists)
            // Note: Always show checkbox, plugin check happens at API request time
            if ($hasSubticketColumn) {
                $html .= '<div class="permission-item">';
                $html .= '<input type="checkbox" name="api_key_subticket_permissions[' . $apiKeyId . ']" ' .
                         'value="1" class="permission-checkbox" id="subticket_' . $apiKeyId . '" ' .
                         ($canSubticket ? 'checked' : '') . ' />';
                $html .= '<label for="subticket_' . $apiKeyId . '">';
                $html .= '<strong>Manage Subtickets</strong>';
                $html .= '<span class="permission-endpoint">Subticket Operations</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            $html .= '</div>'; // Close permissions-grid
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<div class="info-banner" style="padding: 10px; margin-top: 15px; background: #e3f2fd; border-left: 4px solid #2196F3;">
            <strong>Note:</strong> All permissions can be managed here. Changes take effect immediately after saving.
            The "Can Update Tickets", "Can Read Tickets", "Can Search Tickets", "Can Delete Tickets", "Can Read Stats", and "Can Manage Subtickets" permissions are provided by the API Endpoints plugin.
        </div>';

        $html .= '</div>'; // Close wrapper div

        return $html;
    }
}
