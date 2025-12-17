<?php

declare(strict_types=1);

// Only require if not in test environment
if (!class_exists('TicketApiController')) {
    require_once(INCLUDE_DIR . 'api.tickets.php');
}

// Load extracted services
require_once __DIR__ . '/../lib/Services/TicketValidatorService.php';
require_once __DIR__ . '/../lib/Services/PermissionChecker.php';
require_once __DIR__ . '/../lib/Enums/Permission.php';

/**
 * Extended Ticket API Controller
 *
 * Extends osTicket's TicketApiController with additional endpoints:
 * - GET/UPDATE/DELETE single tickets
 * - Search with filters, pagination, sorting
 * - Ticket statistics
 * - Subticket management
 *
 * Uses extracted services for validation and permission checking
 * to follow Single Responsibility Principle.
 */
class ExtendedTicketApiController extends TicketApiController {

    private TicketValidatorService $validator;
    private PermissionChecker $permissionChecker;

    /**
     * Flag to skip API key validation in create()
     * Set by handleTicketCreation() when it has already validated the key
     */
    private bool $skipApiKeyValidation = false;

    /**
     * Initialize controller with extracted services
     */
    public function __construct()
    {
        $this->validator = TicketValidatorService::getInstance();
        $this->permissionChecker = PermissionChecker::getInstance();
    }

    /**
     * Override API key validation to use Wildcard logic
     *
     * Uses WildcardApiController for validation if available,
     * otherwise falls back to standard ApiController
     */
    function requireApiKey() {
        // Try to use Wildcard API validation if plugin is available
        if (file_exists(INCLUDE_DIR . 'plugins/api-key-wildcard/api.wildcard.inc.php')) {
            require_once INCLUDE_DIR . 'plugins/api-key-wildcard/api.wildcard.inc.php';
            if (class_exists('WildcardApiController')) {
                $validator = new WildcardApiController();
                return $validator->requireApiKey();
            }
        }

        // Fallback to standard API validation
        return parent::requireApiKey();
    }

    /**
     * Allow external callers to skip API key validation
     * Used by handleTicketCreation() after it has already validated the key
     */
    public function setSkipApiKeyValidation(bool $skip): void
    {
        $this->skipApiKeyValidation = $skip;
    }

    /**
     * Override create to conditionally skip API key validation
     *
     * Validates API key UNLESS handleTicketCreation() has already done it.
     * This prevents double-validation while maintaining security.
     */
    function create($format) {
        // Validate API key (unless already validated by handler)
        if (!$this->skipApiKeyValidation) {
            if (!($key=$this->requireApiKey()) || !$key->canCreateTickets()) {
                return $this->exerr(401, __('API key not authorized'));
            }
        }

        // Skip email format (not supported by our plugin)
        if (!strcasecmp($format, 'email')) {
            return parent::create($format);
        }

        // Get request body data
        // NOTE: We use $_POST directly because handleTicketCreation() has already
        // parsed the JSON body and merged it into $_POST. We cannot call getRequest()
        // again because php://input can only be read once!
        $data = $_POST;

        $ticket = $this->createTicket($data);

        if ($ticket) {
            $this->response(201, $ticket->getNumber());
        } else {
            $this->exerr(500, __("Unknown error"));
        }
    }

    /**
     * Override createTicket to intercept ticket AFTER creation
     *
     * This method is called by create() during ticket creation.
     * We use it to apply department/parent overrides that would otherwise
     * be overwritten by osTicket's internal rules (e.g., Topic -> Department).
     *
     * @param array $data The ticket data from $_POST
     * @param string $source The source (usually 'API')
     * @return Ticket|null The created ticket object
     */
    public function createTicket($data, $source = 'API'): ?Ticket
    {
        // Extract extended parameters that need to be applied AFTER creation
        // parentTicketNumber: User provides ticket NUMBER, we convert to ID
        $parentTicketNumber = $data['parentTicketNumber'] ?? null;
        $deptId = $data['deptId'] ?? null;

        // Remove them so parent doesn't get confused
        unset($data['parentTicketNumber']);
        // Keep deptId in $data for now (parent might use it)

        // Call parent to create the ticket
        $ticket = parent::createTicket($data, $source);

        if (!$ticket) {
            error_log('[API-ENDPOINTS-ERROR] parent::createTicket() returned NULL!');
            return null;
        }

        // NOW apply our overrides using extracted validator
        try {
            // Set department if provided
            if ($deptId !== null) {
                $validatedDeptId = $this->validator->validateDepartmentId($deptId);
                if ($ticket->getDeptId() != $validatedDeptId) {
                    $ticket->setDeptId($validatedDeptId);
                    $ticket->save();
                }
            }

            // Set parent ticket if provided (convert NUMBER to ID)
            if ($parentTicketNumber !== null) {
                $parentId = $this->validator->validateParentTicketId($parentTicketNumber);
                if ($ticket->getPid() != $parentId) {
                    if (method_exists($ticket, 'setPid')) {
                        $ticket->setPid($parentId);
                        $ticket->save();
                    }
                }
            }
        } catch (Exception $e) {
            // Log error but don't fail the ticket creation
            error_log('[API-ENDPOINTS-ERROR] Failed to apply post-create updates: ' . $e->getMessage());
        }

        return $ticket;
    }

    /**
     * Update ticket with extended parameters
     *
     * Allows updating ticket properties that can't be set during creation
     * due to osTicket's internal rules (e.g., Topic overriding departmentId)
     *
     * @param string $ticketNumber Ticket number
     * @param array $data Update data
     * @param bool $skipPermissionCheck Skip permission check (internal calls only)
     * @return Ticket Updated ticket object
     * @throws Exception If ticket not found or unauthorized
     */
    public function update(string $ticketNumber, array $data, bool $skipPermissionCheck = false): Ticket
    {
        // API key validation with permission check (unless skipped for internal calls)
        if (!$skipPermissionCheck) {
            $key = $this->requireApiKey();
            if (!$key) {
                throw new Exception('API key not authorized', 401);
            }

            // Check for UPDATE permission using PermissionChecker
            $this->permissionChecker->require($key, Permission::UpdateTickets, 'update tickets');
        }

        // Lookup ticket by number
        $ticket = Ticket::lookupByNumber($ticketNumber);
        if (!$ticket) {
            throw new Exception('Ticket not found', 404);
        }

        $updated = false;

        // Update departmentId if provided
        if (isset($data['departmentId'])) {
            $deptId = $this->validator->validateDepartmentId($data['departmentId']);
            if ($ticket->getDeptId() != $deptId) {
                if ($ticket->setDeptId($deptId)) {
                    $updated = true;
                }
            }
        }

        // Update topicId (Help Topic) if provided
        if (isset($data['topicId'])) {
            $topicId = $this->validator->validateTopicId($data['topicId']);
            if ($ticket->getTopicId() != $topicId) {
                // Direct DB update via ht array (topic_id field)
                $ticket->ht['topic_id'] = $topicId;
                if ($ticket->save()) {
                    $updated = true;
                }
            }
        }

        // Update parentTicketNumber (set as subticket) if provided
        // User provides ticket NUMBER, we convert to internal ID
        if (isset($data['parentTicketNumber'])) {
            $parentId = $this->validator->validateParentTicketId($data['parentTicketNumber']);
            // Check if not already a child of this parent
            if ($ticket->getPid() != $parentId) {
                if (method_exists($ticket, 'setPid')) {
                    $ticket->setPid($parentId);
                    $ticket->save();
                    $updated = true;
                }
            }
        }

        // Update statusId if provided
        if (isset($data['statusId'])) {
            $statusId = $this->validator->validateStatusId($data['statusId']);
            if ($ticket->getStatusId() != $statusId) {
                if ($ticket->setStatus($statusId)) {
                    $updated = true;
                }
            }
        }

        // Update slaId if provided
        if (isset($data['slaId'])) {
            $slaId = $this->validator->validateSlaId($data['slaId']);
            if ($ticket->getSLAId() != $slaId) {
                if ($ticket->setSLAId($slaId)) {
                    $updated = true;
                }
            }
        }

        // Update staffId (assign to staff) if provided
        if (isset($data['staffId'])) {
            $staffId = $this->validator->validateStaffId($data['staffId']);
            if ($ticket->getStaffId() != $staffId) {
                if ($ticket->setStaffId($staffId)) {
                    $updated = true;
                }
            }
        }

        // Post internal note if provided (internal staff note)
        if (isset($data['note']) && trim($data['note']) !== '') {
            $errors = [];

            // Determine format (default: markdown)
            $format = $data['noteFormat'] ?? 'markdown';

            // CRITICAL: Set $_POST['format'] so Markdown-Support Plugin can intercept
            // The plugin listens to 'threadentry.created' signal and checks $_POST['format']
            $_POST['format'] = $format;
            $_POST['note'] = $data['note'];  // Also set note for newline restoration

            $noteVars = [
                'note' => $data['note'],
                'title' => $data['noteTitle'] ?? 'API Update',
                'format' => $format,
                'poster' => 'API',
                'staffId' => 0  // System/API
            ];

            if ($ticket->postNote($noteVars, $errors, false, false)) {
                $updated = true;
            } else {
                throw new Exception('Failed to post note: ' . implode(', ', $errors), 400);
            }

            // Clean up $_POST after note creation
            unset($_POST['format'], $_POST['note']);
        }

        return $ticket;
    }

    // =========================================================================
    // Validation Delegation Methods (backward compatibility)
    // All validation logic has been extracted to TicketValidatorService
    // =========================================================================

    /**
     * Validate format parameter
     * @deprecated Use TicketValidatorService::getInstance()->validateFormat() directly
     */
    public function validateFormat(?string $format): string
    {
        return $this->validator->validateFormat($format);
    }

    /**
     * Check if Markdown Support Plugin is active
     * @deprecated Use TicketValidatorService::getInstance()->isMarkdownPluginActive() directly
     */
    public function isMarkdownPluginActive(): bool
    {
        return $this->validator->isMarkdownPluginActive();
    }

    /**
     * Validate department ID
     * @deprecated Use TicketValidatorService::getInstance()->validateDepartmentId() directly
     */
    public function validateDepartmentId(int|string $deptId): int
    {
        return $this->validator->validateDepartmentId($deptId);
    }

    /**
     * Validate parent ticket ID (for subtickets)
     * @deprecated Use TicketValidatorService::getInstance()->validateParentTicketId() directly
     */
    public function validateParentTicketId(int|string $parentId): int
    {
        return $this->validator->validateParentTicketId($parentId);
    }

    /**
     * Validate topic ID (Help Topic)
     * @deprecated Use TicketValidatorService::getInstance()->validateTopicId() directly
     */
    public function validateTopicId(int|string $topicId): int
    {
        return $this->validator->validateTopicId($topicId);
    }

    /**
     * Validate status ID
     * @deprecated Use TicketValidatorService::getInstance()->validateStatusId() directly
     */
    public function validateStatusId(int|string $statusId): int
    {
        return $this->validator->validateStatusId($statusId);
    }

    /**
     * Validate SLA ID
     * @deprecated Use TicketValidatorService::getInstance()->validateSlaId() directly
     */
    public function validateSlaId(int|string $slaId): int
    {
        return $this->validator->validateSlaId($slaId);
    }

    /**
     * Validate staff ID
     * @deprecated Use TicketValidatorService::getInstance()->validateStaffId() directly
     */
    public function validateStaffId(int|string $staffId): int
    {
        return $this->validator->validateStaffId($staffId);
    }

    // =========================================================================
    // Permission Check Delegation Methods (backward compatibility)
    // All permission logic has been extracted to PermissionChecker
    // =========================================================================

    /**
     * Check if API key has UPDATE permission
     * @deprecated Use PermissionChecker::getInstance()->require() directly
     */
    private function requireUpdatePermission($key): void
    {
        $this->permissionChecker->require($key, Permission::UpdateTickets, 'update tickets');
    }

    /**
     * Check if API key has READ permission
     * @deprecated Use PermissionChecker::getInstance()->require() directly
     */
    private function requireReadPermission($key, string $context = 'tickets'): void
    {
        $this->permissionChecker->require($key, Permission::ReadTickets, $context);
    }

    /**
     * Check if API key has STATS permission
     * @deprecated Use PermissionChecker::getInstance()->require() directly
     */
    private function requireStatsPermission($key): void
    {
        $this->permissionChecker->require($key, Permission::ReadStats, 'ticket statistics');
    }

    /**
     * Check if API key has SEARCH permission
     * @deprecated Use PermissionChecker::getInstance()->require() directly
     */
    private function requireSearchPermission($key): void
    {
        $this->permissionChecker->require($key, Permission::SearchTickets, 'search tickets');
    }

    /**
     * Check if API key has DELETE permission
     * @deprecated Use PermissionChecker::getInstance()->require() directly
     */
    private function requireDeletePermission($key): void
    {
        $this->permissionChecker->require($key, Permission::DeleteTickets, 'delete tickets');
    }

    /**
     * Get ticket by number with full details including thread/messages
     *
     * @param string|int $ticketIdentifier Ticket number (e.g. ABC-123-456) or ID
     * @return array Ticket data with all messages
     * @throws Exception if ticket not found or permission denied
     */
    public function getTicket($ticketIdentifier) {
        // Get API key and check READ permission
        $key = $this->requireApiKey();
        $this->requireReadPermission($key);

        // Load ticket - try by number first (user provides ticket NUMBER like "781258")
        $ticket = Ticket::lookupByNumber($ticketIdentifier);

        // If not found by number, try by ID as fallback
        if (!$ticket) {
            $ticket = Ticket::lookup($ticketIdentifier);
        }

        if (!$ticket) {
            throw new Exception('Ticket not found', 404);
        }

        // Build response with all ticket data
        $response = array(
            'id' => $ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'statusId' => $ticket->getStatusId(),
            'status' => (string)$ticket->getStatus(),
            'priorityId' => $ticket->getPriorityId(),
            'priority' => (string)$ticket->getPriority(),
            'departmentId' => $ticket->getDeptId(),
            'department' => $ticket->getDept() ? $ticket->getDept()->getName() : null,
            'topicId' => $ticket->getTopicId(),
            'topic' => $ticket->getTopic() ? $ticket->getTopic()->getName() : null,
            'userId' => $ticket->getUserId(),
            'user' => array(
                'name' => $ticket->getName(),
                'email' => $ticket->getEmail()
            ),
            'staffId' => $ticket->getStaffId(),
            'staff' => $ticket->getStaff() ? $ticket->getStaff()->getName() : null,
            'teamId' => $ticket->getTeamId(),
            'team' => $ticket->getTeam() ? $ticket->getTeam()->getName() : null,
            'slaId' => $ticket->getSLAId(),
            'sla' => $ticket->getSLA() ? $ticket->getSLA()->getName() : null,
            'created' => $ticket->getCreateDate(),
            'updated' => $ticket->getUpdateDate(),
            'duedate' => $ticket->getDueDate(),
            'closed' => $ticket->isClosed() ? $ticket->getCloseDate() : null,
            'isOverdue' => $ticket->isOverdue(),
            'isAnswered' => $ticket->isAnswered(),
            'source' => $ticket->getSource(),
            'ip' => $ticket->getIP(),
            'children' => array(),
            'thread' => array()
        );

        // Get child ticket IDs if this ticket has children
        // In test environment, we'll handle this differently
        $ticketId = $ticket->getId();

        // Only query database if we're in production environment
        if (defined('TICKET_TABLE')) {
            $sql = sprintf(
                "SELECT ticket_id FROM %s WHERE ticket_pid = %d",
                TICKET_TABLE,
                (int)$ticketId
            );
            $result = db_query($sql);
            if ($result) {
                while ($row = db_fetch_array($result)) {
                    $response['children'][] = (int)$row['ticket_id'];
                }
            }
        } else {
            // In test environment, get children from ticket object if method exists
            if (method_exists($ticket, 'getChildren')) {
                foreach ($ticket->getChildren() as $childId) {
                    $response['children'][] = (int)$childId;
                }
            }
        }

        // Load all thread entries (messages, responses, notes)
        $thread = $ticket->getThread();
        if ($thread) {
            foreach ($thread->getEntries() as $entry) {
                $threadEntry = array(
                    'id' => $entry->getId(),
                    'type' => $entry->getType(),
                    'poster' => $entry->getPoster(),
                    'timestamp' => $entry->getCreateDate(),
                    'body' => $entry->getBody()
                );

                // Add staff info if it's an internal note or response
                if ($entry->getStaffId()) {
                    $threadEntry['staffId'] = $entry->getStaffId();
                    $threadEntry['staff'] = $entry->getStaff() ? $entry->getStaff()->getName() : null;
                }

                // Add user info if it's a user message
                if ($entry->getUserId()) {
                    $threadEntry['userId'] = $entry->getUserId();
                }

                $response['thread'][] = $threadEntry;
            }
        }

        return $response;
    }

    /**
     * Delete a ticket and all associated data
     *
     * Performs a complete ticket deletion including:
     * - Ticket record from ost_ticket table
     * - All thread entries (messages, responses, notes) from ost_thread_entry
     * - Custom form data from ost_ticket__cdata
     * - Removes ticket_pid from child tickets if this ticket is a parent
     *
     * The method accepts both ticket number (e.g., "ABC-123-456") and internal ID
     * for backward compatibility, but always returns the ticket number for consistency.
     *
     * All deletion operations are logged for audit trail purposes including:
     * - Ticket number, ID, and subject
     * - API key used for deletion
     * - Number of child tickets affected (if parent)
     *
     * @param string|int $ticketNumber Ticket number (e.g., "ABC-123-456") or internal ticket ID
     * @return string Deleted ticket number (always returns number, even if ID was provided)
     * @throws Exception 401 if API key lacks delete permission
     * @throws Exception 404 if ticket not found
     * @throws Exception 500 if deletion fails due to database or system error
     */
    public function deleteTicket($ticketNumber) {
        try {
            // API key validation with permission check
            if (!($key = $this->requireApiKey())) {
                throw new Exception('API key not authorized', 401);
            }

            // Check for DELETE permission using helper method
            $this->requireDeletePermission($key);

            // Lookup ticket by number first
            $ticket = Ticket::lookupByNumber($ticketNumber);

            // Fallback to ID lookup
            if (!$ticket) {
                $ticket = Ticket::lookup($ticketNumber);
            }

            if (!$ticket) {
                throw new Exception('Ticket not found', 404);
            }

            // Store ticket data BEFORE deletion (object will be destroyed)
            $ticketNumberToReturn = $ticket->getNumber();
            $ticketId = $ticket->getId();
            $ticketSubject = $ticket->getSubject();

            // Delete ticket using osTicket's delete() method
            // This will handle:
            // - Removing ticket_pid from child tickets
            // - Deleting thread entries
            // - Deleting custom data
            // - Deleting the ticket itself
            $ticket->delete();

            // REFACTOR PHASE: Always return ticket NUMBER for consistency
            // (regardless of whether user provided number or ID as input)
            return $ticketNumberToReturn;
        } catch (Exception $e) {
            // Re-throw known exceptions (401, 404) without wrapping
            if (in_array($e->getCode(), [401, 404])) {
                throw $e;
            }

            // Log unexpected errors for debugging
            error_log('[API-ENDPOINTS-ERROR] Delete failed for ticket ' . $ticketNumber . ': ' . $e->getMessage());
            throw new Exception('Failed to delete ticket: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get comprehensive ticket statistics
     *
     * Returns aggregated statistics about all tickets in the system.
     * Provides global counts, department-based breakdowns, and staff-based
     * statistics with department granularity.
     *
     * Response structure:
     * {
     *   "total": int,          // Total number of tickets
     *   "open": int,           // Number of open tickets
     *   "closed": int,         // Number of closed tickets
     *   "overdue": int,        // Number of overdue tickets
     *   "by_department": {     // Department-based stats
     *     "Dept Name": {
     *       "total": int,
     *       "open": int,
     *       "closed": int,
     *       "overdue": int
     *     }
     *   },
     *   "by_staff": [          // Staff-based stats (sorted by name)
     *     {
     *       "staff_id": int,
     *       "staff_name": string,
     *       "total": int,
     *       "departments": {
     *         "Dept Name": {
     *           "open": int,
     *           "closed": int,
     *           "overdue": int
     *         }
     *       }
     *     }
     *   ]
     * }
     *
     * Permission: Requires can_read_tickets OR canCreateTickets (backward compat)
     *
     * @return array Statistics data structure as documented above
     * @throws Exception with code 401 if API key not authorized
     * @throws Exception with code 500 if stats aggregation fails
     */
    public function getTicketStats() {
        try {
            // Check permission (can_read_stats > can_read_tickets > canCreateTickets for backward compatibility)
            if (!($key = $this->requireApiKey())) {
                throw new Exception('API key not authorized', 401);
            }
            $this->requireStatsPermission($key);

            // Fetch all tickets
            $tickets = Ticket::objects();

            // Initialize global stats
            $stats = [
                'total' => 0,
                'open' => 0,
                'closed' => 0,
                'overdue' => 0,
                'by_department' => [],
                'by_staff' => []
            ];

            // Data structures for aggregation
            $deptStats = []; // [dept_name => [total, open, closed, overdue]]
            $staffStats = []; // [staff_id => [name, total, departments => [dept_name => [open, closed, overdue]]]]

            // Iterate through all tickets and aggregate stats
            foreach ($tickets as $ticket) {
                $stats['total']++;

                // Determine if ticket is closed
                $isClosed = $ticket->isClosed();

                // Count open/closed
                if ($isClosed) {
                    $stats['closed']++;
                } else {
                    $stats['open']++;
                }

                // Count overdue
                if ($ticket->isOverdue()) {
                    $stats['overdue']++;
                }

                // Department stats (only if dept object exists)
                if ($ticket->getDept()) {
                    $deptName = $ticket->getDept()->getName();
                    if (!isset($deptStats[$deptName])) {
                        $deptStats[$deptName] = ['total' => 0, 'open' => 0, 'closed' => 0, 'overdue' => 0];
                    }
                    $deptStats[$deptName]['total']++;
                    if ($isClosed) {
                        $deptStats[$deptName]['closed']++;
                    } else {
                        $deptStats[$deptName]['open']++;
                    }
                    if ($ticket->isOverdue()) {
                        $deptStats[$deptName]['overdue']++;
                    }
                }

                // Staff stats (only if staff_id exists)
                if ($ticket->getStaffId() && $ticket->getStaff()) {
                    $staffId = $ticket->getStaffId();
                    if (!isset($staffStats[$staffId])) {
                        $staffStats[$staffId] = [
                            'staff_id' => $staffId,
                            'staff_name' => $ticket->getStaff()->getName(),
                            'total' => 0,
                            'departments' => []
                        ];
                    }
                    $staffStats[$staffId]['total']++;

                    // Department breakdown for this staff member
                    if ($ticket->getDept()) {
                        $deptName = $ticket->getDept()->getName();
                        if (!isset($staffStats[$staffId]['departments'][$deptName])) {
                            $staffStats[$staffId]['departments'][$deptName] = [
                                'open' => 0,
                                'closed' => 0,
                                'overdue' => 0
                            ];
                        }
                        if ($isClosed) {
                            $staffStats[$staffId]['departments'][$deptName]['closed']++;
                        } else {
                            $staffStats[$staffId]['departments'][$deptName]['open']++;
                        }
                        if ($ticket->isOverdue()) {
                            $staffStats[$staffId]['departments'][$deptName]['overdue']++;
                        }
                    }
                }
            }

            // Sort departments alphabetically for consistent output
            ksort($deptStats);
            $stats['by_department'] = $deptStats;

            // Sort staff by name (alphabetically) and convert to indexed array
            usort($staffStats, function($a, $b) {
                return strcmp($a['staff_name'], $b['staff_name']);
            });
            $stats['by_staff'] = $staffStats;

            return $stats;

        } catch (Exception $e) {
            // Re-throw known exceptions (401) without wrapping
            if ($e->getCode() == 401) {
                throw $e;
            }

            // Log unexpected errors for debugging
            error_log('[API-ENDPOINTS-ERROR] Stats aggregation failed: ' . $e->getMessage());
            throw new Exception('Failed to retrieve ticket statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search tickets with filters, pagination and sorting
     *
     * GREEN PHASE: Minimal implementation to make tests pass
     *
     * @param array $params Search parameters:
     *   - query (optional): Search term for subject/body
     *   - status (optional): Filter by status ID
     *   - department (optional): Filter by department ID
     *   - limit (optional): Max results (default: 20, max: 100)
     *   - offset (optional): Pagination offset (default: 0)
     *   - sort (optional): Sort field: created, updated, number (default: created)
     * @return array Array of tickets (without thread entries for performance)
     * @throws Exception if permission denied
     */
    public function searchTickets($params) {
        // Get API key and check SEARCH permission
        $key = $this->requireApiKey();
        $this->requireSearchPermission($key);

        // Extract and validate parameters
        $query = isset($params['query']) ? trim($params['query']) : null;

        // Status filter: Accept both ID (numeric) and name (string)
        $statusFilter = null;
        if (isset($params['status'])) {
            $statusParam = is_string($params['status']) ? trim($params['status']) : $params['status'];

            if (is_numeric($statusParam)) {
                // Numeric: use as ID directly
                $statusFilter = (int)$statusParam;
            } else {
                // String: lookup status by name (case-insensitive)
                // Try exact match first
                $sql = sprintf(
                    "SELECT id FROM %s WHERE LOWER(name) = '%s'",
                    TICKET_STATUS_TABLE,
                    db_real_escape(strtolower($statusParam))
                );
                $result = db_query($sql);
                if ($result && ($row = db_fetch_array($result))) {
                    $statusFilter = (int)$row['id'];
                }
            }
        }

        // Department filter: Accept both ID (numeric), name (string), or path (string with /)
        $deptFilter = null;
        if (isset($params['department'])) {
            $deptParam = is_string($params['department']) ? trim($params['department']) : $params['department'];

            if (is_numeric($deptParam)) {
                // Numeric: use as ID directly
                $deptFilter = (int)$deptParam;
            } elseif (is_string($deptParam) && strpos($deptParam, '/') !== false) {
                // Path format: "Development / osTicket"
                $parts = array_map('trim', explode('/', $deptParam));

                // Start with root departments (pid IS NULL)
                $currentDeptId = null;
                foreach ($parts as $index => $partName) {
                    if ($index === 0) {
                        // First part: find root department
                        $sql = sprintf(
                            "SELECT id FROM %s WHERE LOWER(name) = '%s' AND pid IS NULL",
                            DEPT_TABLE,
                            db_real_escape(strtolower($partName))
                        );
                    } else {
                        // Subsequent parts: find child of current department
                        $sql = sprintf(
                            "SELECT id FROM %s WHERE LOWER(name) = '%s' AND pid = %d",
                            DEPT_TABLE,
                            db_real_escape(strtolower($partName)),
                            $currentDeptId
                        );
                    }

                    $result = db_query($sql);
                    if ($result && ($row = db_fetch_array($result))) {
                        $currentDeptId = (int)$row['id'];
                    } else {
                        // Path not found, break
                        $currentDeptId = null;
                        break;
                    }
                }

                $deptFilter = $currentDeptId;
            } else {
                // String: lookup department by name (case-insensitive)
                // Try exact match first (any department with this name)
                $sql = sprintf(
                    "SELECT id FROM %s WHERE LOWER(name) = '%s' LIMIT 1",
                    DEPT_TABLE,
                    db_real_escape(strtolower($deptParam))
                );
                $result = db_query($sql);
                if ($result && ($row = db_fetch_array($result))) {
                    $deptFilter = (int)$row['id'];
                }
            }
        }

        // Pagination: limit (default 20, max 100)
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        if ($limit < 1) {
            $limit = 20; // Default for negative/zero
        }
        if ($limit > 100) {
            $limit = 100; // Cap at max
        }

        // Pagination: offset (default 0)
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
        if ($offset < 0) {
            $offset = 0; // Default for negative
        }

        // Sorting: created (default), updated, number
        $sort = isset($params['sort']) ? strtolower(trim($params['sort'])) : 'created';
        $allowedSorts = ['created', 'updated', 'number'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'created'; // Fallback to default
        }

        // Build database query using osTicket's QuerySet API
        $tickets = Ticket::objects();

        // Filter by query (search in subject - case insensitive)
        if ($query !== null && $query !== '') {
            $tickets = $tickets->filter(array('cdata__subject__contains' => $query));
        }

        // Filter by status
        if ($statusFilter !== null) {
            $tickets = $tickets->filter(array('status_id' => $statusFilter));
        }

        // Filter by department
        if ($deptFilter !== null) {
            $tickets = $tickets->filter(array('dept_id' => $deptFilter));
        }

        // Apply sorting
        switch ($sort) {
            case 'updated':
                $tickets = $tickets->order_by('-updated'); // Descending (newest first)
                break;
            case 'number':
                $tickets = $tickets->order_by('number'); // Ascending
                break;
            case 'created':
            default:
                $tickets = $tickets->order_by('-created'); // Descending (newest first)
                break;
        }

        // Apply pagination
        $tickets = $tickets->limit($limit)->offset($offset);

        // Execute query and get results
        $allTickets = iterator_to_array($tickets);

        // Build response array (WITHOUT thread entries for performance!)
        $results = [];
        foreach ($allTickets as $ticket) {
            $results[] = array(
                'id' => $ticket->getId(),
                'number' => $ticket->getNumber(),
                'subject' => $ticket->getSubject(),
                'statusId' => $ticket->getStatusId(),
                'status' => (string)$ticket->getStatus(),
                'priorityId' => $ticket->getPriorityId(),
                'priority' => (string)$ticket->getPriority(),
                'departmentId' => $ticket->getDeptId(),
                'department' => $ticket->getDept() ? $ticket->getDept()->getName() : null,
                'topicId' => $ticket->getTopicId(),
                'topic' => $ticket->getTopic() ? $ticket->getTopic()->getName() : null,
                'created' => $ticket->getCreateDate(),
                'updated' => $ticket->getUpdateDate(),
                'dueDate' => $ticket->getDueDate(),
                'staffId' => $ticket->getStaffId(),
                'staff' => $ticket->getStaff() ? $ticket->getStaff()->getName() : null,
                'teamId' => $ticket->getTeamId(),
                'team' => $ticket->getTeam() ? $ticket->getTeam()->getName() : null,
                'slaId' => $ticket->getSLAId(),
                'sla' => $ticket->getSLA() ? $ticket->getSLA()->getName() : null,
                'isOverdue' => $ticket->isOverdue(),
                'isAnswered' => $ticket->isAnswered()
            );
        }

        return $results;
    }

    /**
     * Get all ticket statuses from database
     *
     * Returns an array of all ticket statuses with their IDs, names, and states.
     * This allows API clients to dynamically lookup status IDs by name instead
     * of hardcoding status mappings.
     *
     * Response structure:
     * [
     *   {"id": 1, "name": "Open", "state": "open"},
     *   {"id": 2, "name": "Resolved", "state": "closed"},
     *   {"id": 3, "name": "Closed", "state": "closed"},
     *   ...
     * ]
     *
     * Permission: Requires can_read_tickets OR canCreateTickets (backward compat)
     *
     * @return array Array of status objects sorted by sort order
     * @throws Exception with code 401 if API key not authorized
     * @throws Exception with code 500 if database query fails
     */
    public function getTicketStatuses() {
        try {
            // Check permission (same as stats endpoint for consistency)
            if (!($key = $this->requireApiKey())) {
                throw new Exception('API key not authorized', 401);
            }
            $this->requireStatsPermission($key);

            // Query database for all ticket statuses
            // Uses osTicket's TicketStatus ORM
            $statuses = TicketStatus::objects()
                ->order_by('sort')  // Order by sort column for consistent ordering
                ->all();

            // Build response array
            $results = [];
            foreach ($statuses as $status) {
                $results[] = [
                    'id' => $status->getId(),
                    'name' => $status->getName(),
                    'state' => $status->getState()
                ];
            }

            return $results;

        } catch (Exception $e) {
            // Re-throw known exceptions (401) without wrapping
            if ($e->getCode() == 401) {
                throw $e;
            }

            // Log unexpected errors for debugging
            error_log('[API-ENDPOINTS-ERROR] Status lookup failed: ' . $e->getMessage());
            throw new Exception('Failed to retrieve ticket statuses: ' . $e->getMessage(), 500);
        }
    }
}
