<?php

// Only include osTicket classes if they exist (not in test environment)
if (defined('INCLUDE_DIR') && file_exists(INCLUDE_DIR . 'class.plugin.php')) {
    require_once INCLUDE_DIR . 'class.plugin.php';
}

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/**
 * API Endpoints Plugin for osTicket 1.18.x
 *
 * Extends osTicket API with additional endpoints and parameters:
 * - POST /tickets with format (markdown/html/text), departmentId, parentTicketId
 * - Future: GET, UPDATE, SEARCH, STATS endpoints
 *
 * Features:
 * - Signal-based architecture (works with both Standard and Wildcard APIs)
 * - Markdown Plugin integration via $_POST['format'] bridge
 * - Subticket support via parentTicketId parameter
 * - Department selection via departmentId parameter
 * - No core file modifications needed
 *
 * Architecture:
 * - Uses Signal::connect('api') for route registration
 * - ExtendedTicketApiController extends TicketApiController
 * - Validation methods in controller (TDD-covered)
 */
class ApiEndpointsPlugin extends Plugin {
    var $config_class = 'ApiEndpointsConfig';

    // Static config cache for Signal callbacks
    // Signal callbacks get a new instance without proper config
    // So we cache config values statically during bootstrap
    static $cached_config = null;

    /**
     * Only one instance of this plugin makes sense
     */
    function isSingleton() {
        return true;
    }

    /**
     * Bootstrap plugin - called when osTicket initializes
     */
    function bootstrap() {
        // Get config from the REAL instance (has proper ID)
        $config = $this->getConfig();

        // Cache config values statically for Signal callbacks
        self::$cached_config = [
            'enabled' => $config->get('enabled'),
            'endpoint_create_ticket' => $config->get('endpoint_create_ticket'),
            'endpoint_update_ticket' => $config->get('endpoint_update_ticket'),
            'require_markdown_plugin' => $config->get('require_markdown_plugin'),
            'installed_version' => $config->get('installed_version')
        ];

        // Version tracking and auto-update
        $this->checkVersion();

        // Only register API routes if plugin is enabled
        if (!self::$cached_config['enabled']) {
            return;
        }

        // Register API routes via Signal
        // This works for BOTH Standard API and Wildcard API
        Signal::connect('api', array($this, 'onApiRequest'));
    }


    /**
     * Inject Admin UI extensions (JavaScript for API Key form)
     *
     * Called during bootstrap() to inject JavaScript into API Key admin pages
     */
    function injectAdminUi() {
        global $ost;

        // Check if we're on the API Keys page
        if (!isset($_SERVER['SCRIPT_NAME']) ||
            !preg_match('#/scp/apikeys\.php#', $_SERVER['SCRIPT_NAME'])) {
            return;
        }

        // Check if $ost is available
        if (!$ost) {
            return;
        }

        // Get API key data if editing existing key
        $apiKeyId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $canUpdateTickets = '0';

        if ($apiKeyId) {
            // Secure: Cast to integer to prevent SQL injection
            $sql = sprintf(
                'SELECT can_update_tickets FROM %s WHERE id = %d',
                API_KEY_TABLE,
                (int)$apiKeyId
            );
            $result = db_query($sql);
            if ($result && ($row = db_fetch_array($result))) {
                $canUpdateTickets = $row['can_update_tickets'] ?? '0';
            }
        }

        // Build JavaScript code
        $pluginUrl = $this->getPluginUrl();
        $jsCode = sprintf(
            '<script>
            console.log("API Endpoints Plugin: Injecting admin UI extension");
            console.log("Plugin URL: %s");
            console.log("Can Update Tickets Value: %s");
            // Pass current value to JavaScript via hidden input
            $(document).ready(function() {
                $("<input type=\"hidden\" name=\"can_update_tickets_value\" value=\"%s\">").appendTo("form");
            });
            </script>
            <script src="%sassets/admin-apikey-extension.js"></script>',
            $pluginUrl,
            $canUpdateTickets,
            $canUpdateTickets,
            $pluginUrl
        );

        // Inject into page header
        $ost->addExtraHeader($jsCode);
    }

    /**
     * Handle API Key form submission
     *
     * Saves can_update_tickets permission when API Key form is submitted
     */
    function handleApiKeyFormSubmission() {
        // Check if this is an API Key form submission
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
            !isset($_SERVER['SCRIPT_NAME']) ||
            !preg_match('#/scp/apikeys\.php#', $_SERVER['SCRIPT_NAME'])) {
            return;
        }

        // Check if this is an add or update action
        if (!isset($_POST['do']) || !in_array($_POST['do'], ['add', 'update'])) {
            return;
        }

        // Get the can_update_tickets value from POST
        $canUpdateTickets = isset($_POST['can_update_tickets']) ? 1 : 0;

        // For add action, we need to wait until after the key is created
        // We'll use a shutdown function to update it
        if ($_POST['do'] === 'add') {
            $plugin = $this;
            register_shutdown_function(function() use ($canUpdateTickets, $plugin) {
                $plugin->updateLatestApiKeyPermission($canUpdateTickets);
            });
        }
        // For update action, we can update directly
        else if ($_POST['do'] === 'update' && isset($_POST['id'])) {
            $this->updateApiKeyPermission((int)$_POST['id'], $canUpdateTickets);
        }
    }

    /**
     * Update can_update_tickets permission for a specific API Key
     *
     * @param int $apiKeyId API Key ID
     * @param int $canUpdate Permission value (0 or 1)
     */
    function updateApiKeyPermission($apiKeyId, $canUpdate) {
        $sql = sprintf(
            'UPDATE %s SET can_update_tickets = %d WHERE id = %d',
            API_KEY_TABLE,
            $canUpdate ? 1 : 0,
            $apiKeyId
        );

        db_query($sql);
    }

    /**
     * Update can_update_tickets permission for the latest created API Key
     *
     * @param int $canUpdate Permission value (0 or 1)
     */
    function updateLatestApiKeyPermission($canUpdate) {
        // Get the latest API key ID
        $sql = sprintf(
            'SELECT id FROM %s ORDER BY id DESC LIMIT 1',
            API_KEY_TABLE
        );

        $result = db_query($sql);
        if ($result && ($row = db_fetch_array($result))) {
            $this->updateApiKeyPermission($row['id'], $canUpdate);
        }
    }


    /**
     * Get plugin URL for assets
     *
     * @return string Plugin URL
     */
    function getPluginUrl() {
        $pluginDir = basename(dirname(__FILE__));
        return ROOT_PATH . 'include/plugins/' . $pluginDir . '/';
    }

    /**
     * Signal handler for API requests
     *
     * Called when osTicket's API receives a request (both Standard and Wildcard APIs)
     * The signal is sent with the dispatcher BEFORE routes are resolved,
     * so we can add our own routes to handle extended parameters.
     *
     * @param PatternFile $dispatcher API route dispatcher
     */
    function onApiRequest($dispatcher) {
        // Check if plugin is enabled (from cached config)
        if (!self::$cached_config || !self::$cached_config['enabled']) {
            return;
        }

        // Load ExtendedTicketApiController
        require_once __DIR__ . '/controllers/ExtendedTicketApiController.php';

        // Add our extended routes BEFORE the default routes
        // Note: Dispatcher class has no prepend() method, so we use array_unshift()

        // Route 1: POST /tickets.json - Extended ticket creation
        if (self::$cached_config['endpoint_create_ticket']) {
            array_unshift($dispatcher->urls,
                url_post("^/tickets\.(?P<format>xml|json|email)$",
                    array($this, 'handleTicketCreation')
                )
            );
        }

        // Route 2: PATCH /tickets/{number}.json - Ticket update
        if (self::$cached_config['endpoint_update_ticket']) {
            array_unshift($dispatcher->urls,
                url("^/tickets/(?P<number>[^/]+)\.(?P<format>json|xml)$",
                    array($this, 'handleTicketUpdate'),
                    false,  // $args
                    array('PATCH', 'PUT')  // $method
                )
            );
        }
    }

    /**
     * Handle ticket creation with extended parameters
     *
     * Note: API key validation is handled by ExtendedTicketApiController->requireApiKey()
     * which is called internally by create() method
     *
     * @param string $format Response format (json|xml|email)
     * @return mixed API response
     */
    function handleTicketCreation($format) {
        // Create controller instance (pass null as we'll use $_POST directly)
        // API key validation is done internally by create() via requireApiKey()
        $controller = new ExtendedTicketApiController(null);

        // Validate and process extended parameters BEFORE ticket creation
        try {
            // Parse JSON body if Content-Type is application/json
            if (isset($_SERVER['CONTENT_TYPE']) &&
                strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
                $body = file_get_contents('php://input');
                $data = json_decode($body, true);
                if ($data) {
                    $_POST = array_merge($_POST, $data);
                }
            }

            // 1. Validate format parameter (if provided)
            if (isset($_POST['format'])) {
                $format_param = $controller->validateFormat($_POST['format']);

                // Check if Markdown plugin is required and active
                if ($format_param === 'markdown' &&
                    self::$cached_config['require_markdown_plugin']) {
                    if (!$controller->isMarkdownPluginActive()) {
                        throw new Exception(
                            'Markdown Support plugin is required but not active', 400
                        );
                    }
                }
            }

            // 2. Validate departmentId parameter (if provided)
            if (isset($_POST['departmentId'])) {
                $deptId = $controller->validateDepartmentId($_POST['departmentId']);
                // CRITICAL: Map to 'deptId', NOT 'topicId'!
                // deptId is used for department routing (see class.ticket.php:4279)
                $_POST['deptId'] = $deptId;
            }

            // 3. Validate parentTicketNumber parameter (if provided)
            // User provides the visible ticket NUMBER, we convert to internal ticket ID
            // Note: Validation happens here, but parent-child relationship
            // is set in ExtendedTicketApiController->createTicket() AFTER ticket creation
            if (isset($_POST['parentTicketNumber'])) {
                $parentId = $controller->validateParentTicketId($_POST['parentTicketNumber']);
                $_POST['parentTicketNumber'] = $parentId; // Store the resolved ID
            }

            // All validations passed - delegate to controller
            // Controller will handle parent-child relationship internally
            $ticketController = new ExtendedTicketApiController(null);

            // Skip API key validation in create() - we already validated it above
            $ticketController->setSkipApiKeyValidation(true);

            $result = $ticketController->create($format);
            return $result;

        } catch (Exception $e) {
            // Validation failed - return error response
            Http::response(400, $e->getMessage(), 'text/plain');
            return;
        }
    }

    /**
     * Handle ticket update with extended parameters
     *
     * Supports PATCH/PUT requests to update ticket properties that can't be set
     * during creation due to osTicket's internal rules (e.g., departmentId)
     *
     * @param string $number Ticket number
     * @param string $format Response format (json|xml)
     * @return mixed API response
     */
    function handleTicketUpdate($number, $format) {
        // Create controller instance
        $controller = new ExtendedTicketApiController(null);

        try {
            // Parse JSON body
            if (isset($_SERVER['CONTENT_TYPE']) &&
                strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
                $body = file_get_contents('php://input');
                $data = json_decode($body, true);
                if (!$data) {
                    throw new Exception('Invalid JSON body', 400);
                }
            } elseif ($_POST) {
                $data = $_POST;
            } else {
                throw new Exception('No data provided', 400);
            }

            // Delegate to controller - handles validation and update logic
            $ticket = $controller->update($number, $data);

            // Return success with ticket number
            Http::response(200, $ticket->getNumber(), 'text/plain');

        } catch (Exception $e) {
            // Update failed - return appropriate error response
            $code = $e->getCode() ?: 400;
            if ($code == 401) {
                Http::response(401, $e->getMessage(), 'text/plain');
            } elseif ($code == 404) {
                Http::response(404, $e->getMessage(), 'text/plain');
            } else {
                Http::response(400, $e->getMessage(), 'text/plain');
            }
            return;
        }
    }

    /**
     * Check plugin version and perform updates if needed
     */
    function checkVersion() {
        $plugin_file = INCLUDE_DIR . 'plugins/' . basename(dirname(__FILE__)) . '/plugin.php';

        if (!file_exists($plugin_file)) {
            return;
        }

        $plugin_info = include($plugin_file);
        $current_version = $plugin_info['version'];
        $installed_version = $this->getConfig()->get('installed_version');

        if (!$installed_version || version_compare($installed_version, $current_version, '<')) {
            $this->performUpdate($installed_version, $current_version);
        }
    }

    /**
     * Perform plugin update
     *
     * @param string $from_version Old version
     * @param string $to_version New version
     */
    function performUpdate($from_version, $to_version) {
        $errors = array();

        // Extend API Key table (adds missing columns if needed)
        $this->extendApiKeyTable($errors);

        // Re-deploy API files on update (ensures latest version)
        $this->deployApiFiles($errors);

        // Save new version
        $this->getConfig()->set('installed_version', $to_version);
    }

    /**
     * Called when plugin is enabled in admin panel
     */
    function enable() {
        $errors = array();

        // Auto-create instance for singleton plugin
        if ($this->isSingleton() && $this->getNumInstances() === 0) {
            $vars = array(
                'name' => $this->getName(),
                'isactive' => 1,
                'notes' => 'Auto-created singleton instance'
            );

            if (!$this->addInstance($vars, $errors)) {
                return $errors;
            }
        }

        // Extend API Key table with new permissions
        $this->extendApiKeyTable($errors);

        // Deploy API file to /api/
        $this->deployApiFiles($errors);

        // Get current version from plugin.php
        $plugin_file = INCLUDE_DIR . 'plugins/' . basename(dirname(__FILE__)) . '/plugin.php';

        if (file_exists($plugin_file)) {
            $plugin_info = include($plugin_file);
            $this->getConfig()->set('installed_version', $plugin_info['version']);
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Helper: Add column to API key table if it doesn't exist
     *
     * @param string $columnName Column name to add
     * @param string $columnDefinition Full column definition (e.g., "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0")
     * @param string $afterColumn Column to add after (optional)
     * @param array $errors Error messages array (by reference)
     * @return bool True on success
     */
    private function addColumnIfNotExists($columnName, $columnDefinition, $afterColumn = null, &$errors = array()) {
        $table = API_KEY_TABLE;

        // Secure: Escape column name for LIKE query
        $escapedColumnName = db_real_escape($columnName);
        $sql = sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $table, $escapedColumnName);
        $result = db_query($sql);

        if (!$result || db_num_rows($result) == 0) {
            // Column doesn't exist, add it
            $alterSql = sprintf("ALTER TABLE `%s` ADD COLUMN `%s` %s",
                $table,
                $columnName,  // Column name in backticks for safety
                $columnDefinition
            );

            if ($afterColumn) {
                $alterSql .= sprintf(" AFTER `%s`", $afterColumn);
            }

            if (!db_query($alterSql)) {
                $errors[] = sprintf('Failed to add %s column to API key table', $columnName);
                return false;
            }
        }

        return true;
    }

    /**
     * Extend API Key table with new permissions
     *
     * @param array $errors Error messages array (by reference)
     * @return bool True on success
     */
    function extendApiKeyTable(&$errors) {
        $success = true;

        // Define columns to add with their definitions and position
        $columns = array(
            array(
                'name' => 'can_update_tickets',
                'definition' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
                'after' => 'can_create_tickets'
            ),
            array(
                'name' => 'can_read_tickets',
                'definition' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
                'after' => 'can_update_tickets'
            ),
            array(
                'name' => 'can_search_tickets',
                'definition' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
                'after' => 'can_read_tickets'
            ),
            array(
                'name' => 'can_delete_tickets',
                'definition' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
                'after' => 'can_search_tickets'
            ),
            array(
                'name' => 'can_read_stats',
                'definition' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
                'after' => 'can_delete_tickets'
            ),
            array(
                'name' => 'can_manage_subtickets',
                'definition' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
                'after' => 'can_read_stats'
            )
        );

        // Add each column using helper method
        foreach ($columns as $column) {
            if (!$this->addColumnIfNotExists(
                $column['name'],
                $column['definition'],
                $column['after'],
                $errors
            )) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Helper: Remove column from API key table if it exists
     *
     * @param string $columnName Column name to remove
     * @return bool True on success
     */
    private function removeColumnIfExists($columnName) {
        $table = API_KEY_TABLE;

        // Secure: Escape column name for LIKE query
        $escapedColumnName = db_real_escape($columnName);
        $sql = sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $table, $escapedColumnName);
        $result = db_query($sql);

        if ($result && db_num_rows($result) > 0) {
            // Column exists, remove it
            $sql = sprintf("ALTER TABLE `%s` DROP COLUMN `%s`", $table, $columnName);
            if (!db_query($sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove extended columns from API Key table
     *
     * @return bool True on success
     */
    function removeApiKeyTableExtensions() {
        $success = true;

        // List of columns to remove (in reverse order of adding)
        $columnsToRemove = array(
            'can_manage_subtickets',
            'can_read_stats',
            'can_delete_tickets',
            'can_search_tickets',
            'can_read_tickets',
            'can_update_tickets'
        );

        // Remove each column using helper method
        foreach ($columnsToRemove as $columnName) {
            if (!$this->removeColumnIfExists($columnName)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Deploy API files to /api/ directory
     *
     * @param array $errors Error messages array (by reference)
     * @return bool True on success
     */
    function deployApiFiles(&$errors) {
        $success = true;

        // Deploy tickets-update.php
        $update_source = __DIR__ . '/api/tickets-update.php';
        $update_target = INCLUDE_DIR . '../api/tickets-update.php';

        if (!file_exists($update_source)) {
            $errors[] = 'API file not found: ' . $update_source;
            $success = false;
        } elseif (!copy($update_source, $update_target)) {
            $errors[] = 'Failed to deploy tickets-update.php to /api/';
            $success = false;
        }

        // Deploy tickets-get.php
        $get_source = __DIR__ . '/api/tickets-get.php';
        $get_target = INCLUDE_DIR . '../api/tickets-get.php';

        if (!file_exists($get_source)) {
            $errors[] = 'API file not found: ' . $get_source;
            $success = false;
        } elseif (!copy($get_source, $get_target)) {
            $errors[] = 'Failed to deploy tickets-get.php to /api/';
            $success = false;
        }

        // Deploy tickets-search.php
        $search_source = __DIR__ . '/api/tickets-search.php';
        $search_target = INCLUDE_DIR . '../api/tickets-search.php';

        if (!file_exists($search_source)) {
            $errors[] = 'API file not found: ' . $search_source;
            $success = false;
        } elseif (!copy($search_source, $search_target)) {
            $errors[] = 'Failed to deploy tickets-search.php to /api/';
            $success = false;
        }

        // Deploy tickets-delete.php
        $delete_source = __DIR__ . '/api/tickets-delete.php';
        $delete_target = INCLUDE_DIR . '../api/tickets-delete.php';

        if (!file_exists($delete_source)) {
            $errors[] = 'API file not found: ' . $delete_source;
            $success = false;
        } elseif (!copy($delete_source, $delete_target)) {
            $errors[] = 'Failed to deploy tickets-delete.php to /api/';
            $success = false;
        }

        // Deploy tickets-stats.php
        $stats_source = __DIR__ . '/api/tickets-stats.php';
        $stats_target = INCLUDE_DIR . '../api/tickets-stats.php';

        if (!file_exists($stats_source)) {
            $errors[] = 'API file not found: ' . $stats_source;
            $success = false;
        } elseif (!copy($stats_source, $stats_target)) {
            $errors[] = 'Failed to deploy tickets-stats.php to /api/';
            $success = false;
        }

        // Deploy tickets-subtickets-parent.php
        $subticket_parent_source = __DIR__ . '/api/tickets-subtickets-parent.php';
        $subticket_parent_target = INCLUDE_DIR . '../api/tickets-subtickets-parent.php';

        if (!file_exists($subticket_parent_source)) {
            $errors[] = 'API file not found: ' . $subticket_parent_source;
            $success = false;
        } elseif (!copy($subticket_parent_source, $subticket_parent_target)) {
            $errors[] = 'Failed to deploy tickets-subtickets-parent.php to /api/';
            $success = false;
        }

        // Deploy tickets-subtickets-list.php
        $subticket_list_source = __DIR__ . '/api/tickets-subtickets-list.php';
        $subticket_list_target = INCLUDE_DIR . '../api/tickets-subtickets-list.php';

        if (!file_exists($subticket_list_source)) {
            $errors[] = 'API file not found: ' . $subticket_list_source;
            $success = false;
        } elseif (!copy($subticket_list_source, $subticket_list_target)) {
            $errors[] = 'Failed to deploy tickets-subtickets-list.php to /api/';
            $success = false;
        }

        // Deploy tickets-subtickets-create.php
        $subticket_create_source = __DIR__ . '/api/tickets-subtickets-create.php';
        $subticket_create_target = INCLUDE_DIR . '../api/tickets-subtickets-create.php';

        if (!file_exists($subticket_create_source)) {
            $errors[] = 'API file not found: ' . $subticket_create_source;
            $success = false;
        } elseif (!copy($subticket_create_source, $subticket_create_target)) {
            $errors[] = 'Failed to deploy tickets-subtickets-create.php to /api/';
            $success = false;
        }

        // Add .htaccess rule if not present
        $this->addHtaccessRule($errors);

        return $success;
    }

    /**
     * Add rewrite rule to /api/.htaccess
     *
     * @param array $errors Error messages array (by reference)
     * @return bool True on success
     */
    function addHtaccessRule(&$errors) {
        $htaccess_file = INCLUDE_DIR . '../api/.htaccess';

        // Check if .htaccess exists
        if (!file_exists($htaccess_file)) {
            $errors[] = 'Warning: .htaccess not found in /api/ - you may need to add rewrite rule manually';
            return false;
        }

        // Read current .htaccess
        $content = file_get_contents($htaccess_file);

        $updated = false;

        // Check and add tickets-update.php rule if not present
        if (strpos($content, 'tickets-update\.php/') === false) {
            $update_rule = "\n# Ticket Update API endpoint (pass through without rewriting)\nRewriteRule ^tickets-update\.php/ - [L]\n";

            // Find the wildcard rule
            $wildcard_pos = strpos($content, 'RewriteRule ^wildcard/');
            if ($wildcard_pos === false) {
                $errors[] = 'Warning: Could not find wildcard rule in .htaccess - adding rule at end';
                // Add at end before </IfModule>
                $content = str_replace('</IfModule>', $update_rule . "\n</IfModule>", $content);
            } else {
                // Find end of wildcard rule line
                $line_end = strpos($content, "\n", $wildcard_pos);
                // Insert after wildcard rule
                $content = substr_replace($content, $update_rule, $line_end + 1, 0);
            }
            $updated = true;
        }

        // Check and add tickets-get.php rule if not present
        if (strpos($content, 'tickets-get\.php/') === false) {
            $get_rule = "\n# Ticket Get API endpoint (pass through without rewriting)\nRewriteRule ^tickets-get\.php/ - [L]\n";

            // Find the tickets-update rule (should be there now)
            $update_pos = strpos($content, 'RewriteRule ^tickets-update\.php/');
            if ($update_pos !== false) {
                // Insert after tickets-update rule
                $line_end = strpos($content, "\n", $update_pos);
                $content = substr_replace($content, $get_rule, $line_end + 1, 0);
            } else {
                // Fallback: add at end before </IfModule>
                $content = str_replace('</IfModule>', $get_rule . "\n</IfModule>", $content);
            }
            $updated = true;
        }

        // Check and add tickets-search.php rule if not present
        if (strpos($content, 'tickets-search\.php') === false) {
            $search_rule = "\n# Ticket Search API endpoint (pass through without rewriting)\nRewriteRule ^tickets-search\.php - [L]\n";

            // Find the tickets-get rule (should be there now)
            $get_pos = strpos($content, 'RewriteRule ^tickets-get\.php/');
            if ($get_pos !== false) {
                // Insert after tickets-get rule
                $line_end = strpos($content, "\n", $get_pos);
                $content = substr_replace($content, $search_rule, $line_end + 1, 0);
            } else {
                // Fallback: add at end before </IfModule>
                $content = str_replace('</IfModule>', $search_rule . "\n</IfModule>", $content);
            }
            $updated = true;
        }

        // Check and add tickets-delete.php rule if not present
        if (strpos($content, 'tickets-delete\.php/') === false) {
            $delete_rule = "\n# Ticket Delete API endpoint (pass through without rewriting)\nRewriteRule ^tickets-delete\.php/ - [L]\n";

            // Find the tickets-search rule (should be there now)
            $search_pos = strpos($content, 'RewriteRule ^tickets-search\.php');
            if ($search_pos !== false) {
                // Insert after tickets-search rule
                $line_end = strpos($content, "\n", $search_pos);
                $content = substr_replace($content, $delete_rule, $line_end + 1, 0);
            } else {
                // Fallback: add at end before </IfModule>
                $content = str_replace('</IfModule>', $delete_rule . "\n</IfModule>", $content);
            }
            $updated = true;
        }

        // Check and add tickets-stats.php rule if not present
        if (strpos($content, 'tickets-stats\.php') === false) {
            $stats_rule = "\n# Ticket Stats API endpoint (pass through without rewriting)\nRewriteRule ^tickets-stats\.php - [L]\n";

            // Find the tickets-delete rule (should be there now)
            $delete_pos = strpos($content, 'RewriteRule ^tickets-delete\.php');
            if ($delete_pos !== false) {
                // Insert after tickets-delete rule
                $line_end = strpos($content, "\n", $delete_pos);
                $content = substr_replace($content, $stats_rule, $line_end + 1, 0);
            } else {
                // Fallback: add at end before </IfModule>
                $content = str_replace('</IfModule>', $stats_rule . "\n</IfModule>", $content);
            }
            $updated = true;
        }

        // Check and add tickets-subtickets-parent.php rule if not present
        if (strpos($content, 'tickets-subtickets-parent\.php/') === false) {
            $subticket_parent_rule = "\n# Subticket Parent API endpoint (pass through without rewriting)\nRewriteRule ^tickets-subtickets-parent\.php/ - [L]\n";

            // Find the tickets-stats rule (should be there now)
            $stats_pos = strpos($content, 'RewriteRule ^tickets-stats\.php');
            if ($stats_pos !== false) {
                // Insert after tickets-stats rule
                $line_end = strpos($content, "\n", $stats_pos);
                $content = substr_replace($content, $subticket_parent_rule, $line_end + 1, 0);
            } else {
                // Fallback: add at end before </IfModule>
                $content = str_replace('</IfModule>', $subticket_parent_rule . "\n</IfModule>", $content);
            }
            $updated = true;
        }

        // Check and add tickets-subtickets-list.php rule if not present
        if (strpos($content, 'tickets-subtickets-list\.php/') === false) {
            $subticket_list_rule = "\n# Subticket List API endpoint (pass through without rewriting)\nRewriteRule ^tickets-subtickets-list\.php/ - [L]\n";

            // Find the tickets-subtickets-parent rule (should be there now)
            $parent_pos = strpos($content, 'RewriteRule ^tickets-subtickets-parent\.php');
            if ($parent_pos !== false) {
                // Insert after tickets-subtickets-parent rule
                $line_end = strpos($content, "\n", $parent_pos);
                $content = substr_replace($content, $subticket_list_rule, $line_end + 1, 0);
            } else {
                // Fallback: add at end before </IfModule>
                $content = str_replace('</IfModule>', $subticket_list_rule . "\n</IfModule>", $content);
            }
            $updated = true;
        }

        // Check and add tickets-subtickets-create.php rule if not present
        if (strpos($content, 'tickets-subtickets-create\.php') === false) {
            $subticket_create_rule = "\n# Subticket Create API endpoint (pass through without rewriting)\nRewriteRule ^tickets-subtickets-create\.php - [L]\n";

            // Find the tickets-subtickets-list rule (should be there now)
            $list_pos = strpos($content, 'RewriteRule ^tickets-subtickets-list\.php');
            if ($list_pos !== false) {
                // Insert after tickets-subtickets-list rule
                $line_end = strpos($content, "\n", $list_pos);
                $content = substr_replace($content, $subticket_create_rule, $line_end + 1, 0);
            } else {
                // Fallback: add at end before </IfModule>
                $content = str_replace('</IfModule>', $subticket_create_rule . "\n</IfModule>", $content);
            }
            $updated = true;
        }

        // Write back to file only if changes were made
        if ($updated) {
            if (!file_put_contents($htaccess_file, $content)) {
                $errors[] = 'Failed to update .htaccess - you may need to add rewrite rule manually';
                return false;
            }
        }

        return true;
    }

    /**
     * Remove rewrite rules from /api/.htaccess
     *
     * @return bool True on success
     */
    function removeHtaccessRule() {
        $htaccess_file = INCLUDE_DIR . '../api/.htaccess';

        if (!file_exists($htaccess_file)) {
            return true;
        }

        $content = file_get_contents($htaccess_file);

        // Remove tickets-update.php rule block (including comment)
        $pattern = '/\n# Ticket Update API endpoint.*?\nRewriteRule \^tickets-update\\\.php\/ - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        // Remove tickets-get.php rule block (including comment)
        $pattern = '/\n# Ticket Get API endpoint.*?\nRewriteRule \^tickets-get\\\.php\/ - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        // Remove tickets-search.php rule block (including comment)
        $pattern = '/\n# Ticket Search API endpoint.*?\nRewriteRule \^tickets-search\\\.php - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        // Remove tickets-delete.php rule block (including comment)
        $pattern = '/\n# Ticket Delete API endpoint.*?\nRewriteRule \^tickets-delete\\\.php\/ - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        // Remove tickets-stats.php rule block (including comment)
        $pattern = '/\n# Ticket Stats API endpoint.*?\nRewriteRule \^tickets-stats\\\.php - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        // Remove tickets-subtickets-parent.php rule block (including comment)
        $pattern = '/\n# Subticket Parent API endpoint.*?\nRewriteRule \^tickets-subtickets-parent\\\.php\/ - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        // Remove tickets-subtickets-list.php rule block (including comment)
        $pattern = '/\n# Subticket List API endpoint.*?\nRewriteRule \^tickets-subtickets-list\\\.php\/ - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        // Remove tickets-subtickets-create.php rule block (including comment)
        $pattern = '/\n# Subticket Create API endpoint.*?\nRewriteRule \^tickets-subtickets-create\\\.php - \[L\]\n/s';
        $content = preg_replace($pattern, "\n", $content);

        file_put_contents($htaccess_file, $content);

        return true;
    }

    /**
     * Remove deployed API files from /api/ directory
     *
     * @return bool True on success
     */
    function removeApiFiles() {
        // Remove .htaccess rules
        $this->removeHtaccessRule();

        // Remove tickets-update.php
        $update_target = INCLUDE_DIR . '../api/tickets-update.php';
        if (file_exists($update_target)) {
            @unlink($update_target);
        }

        // Remove tickets-get.php
        $get_target = INCLUDE_DIR . '../api/tickets-get.php';
        if (file_exists($get_target)) {
            @unlink($get_target);
        }

        // Remove tickets-search.php
        $search_target = INCLUDE_DIR . '../api/tickets-search.php';
        if (file_exists($search_target)) {
            @unlink($search_target);
        }

        // Remove tickets-delete.php
        $delete_target = INCLUDE_DIR . '../api/tickets-delete.php';
        if (file_exists($delete_target)) {
            @unlink($delete_target);
        }

        // Remove tickets-stats.php
        $stats_target = INCLUDE_DIR . '../api/tickets-stats.php';
        if (file_exists($stats_target)) {
            @unlink($stats_target);
        }

        // Remove tickets-subtickets-parent.php
        $subticket_parent_target = INCLUDE_DIR . '../api/tickets-subtickets-parent.php';
        if (file_exists($subticket_parent_target)) {
            @unlink($subticket_parent_target);
        }

        // Remove tickets-subtickets-list.php
        $subticket_list_target = INCLUDE_DIR . '../api/tickets-subtickets-list.php';
        if (file_exists($subticket_list_target)) {
            @unlink($subticket_list_target);
        }

        // Remove tickets-subtickets-create.php
        $subticket_create_target = INCLUDE_DIR . '../api/tickets-subtickets-create.php';
        if (file_exists($subticket_create_target)) {
            @unlink($subticket_create_target);
        }

        return true;
    }

    /**
     * Called when plugin is disabled in admin panel
     */
    function disable() {
        // Remove deployed API files
        $this->removeApiFiles();

        return true;
    }

    /**
     * Called when plugin is uninstalled
     */
    function uninstall(&$errors) {
        // Remove deployed API files
        $this->removeApiFiles();

        // Optional: Remove API Key table extensions
        // Note: This will delete the can_update_tickets column and all permission data!
        // Uncomment if you want to clean up completely on uninstall
        // $this->removeApiKeyTableExtensions();

        return parent::uninstall($errors);
    }
}
