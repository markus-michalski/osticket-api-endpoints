<?php

declare(strict_types=1);

// Stub for external plugin class (Intelephense type-checking)
if (false) {
    /** @noinspection PhpMultipleClassDeclarationsInspection */
    class WildcardApiController {
        /** @return bool */
        public function requireApiKey() { return true; }
    }
}

// Only require if not in test environment
if (!class_exists('TicketApiController')) {
    require_once(INCLUDE_DIR . 'api.tickets.php');
}

// Load extracted services
require_once __DIR__ . '/../lib/Services/TicketValidatorService.php';
require_once __DIR__ . '/../lib/Services/PermissionChecker.php';
require_once __DIR__ . '/../lib/Services/TicketService.php';
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
 * Uses extracted services for validation, permission checking,
 * and ticket operations to follow Single Responsibility Principle.
 */
class ExtendedTicketApiController extends TicketApiController {

    private TicketValidatorService $validator;
    private PermissionChecker $permissionChecker;
    private TicketService $ticketService;

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
        $this->ticketService = TicketService::getInstance();
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
                $validator = new \WildcardApiController();
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
            /** @disregard P1013 (Plugin class may not exist in test environment) */
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

        // Update dueDate if provided
        if (array_key_exists('dueDate', $data)) {
            $validatedDueDate = $this->validator->validateDueDate($data['dueDate']);

            // Only update if different (normalize both for comparison)
            $currentDueDate = $ticket->getDueDate();
            if ($validatedDueDate !== $currentDueDate) {
                // osTicket stores duedate via ht array
                $ticket->ht['duedate'] = $validatedDueDate;
                if ($ticket->save()) {
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
    public function getTicket(string|int $ticketIdentifier): array
    {
        // Get API key and check READ permission
        $key = $this->requireApiKey();
        $this->requireReadPermission($key);

        // Delegate to TicketService
        return $this->ticketService->getTicket($ticketIdentifier);
    }

    /**
     * Delete a ticket and all associated data
     *
     * @param string|int $ticketNumber Ticket number or internal ticket ID
     * @return string Deleted ticket number
     * @throws Exception 401 if API key lacks delete permission
     * @throws Exception 404 if ticket not found
     * @throws Exception 500 if deletion fails
     */
    public function deleteTicket(string|int $ticketNumber): string
    {
        try {
            // API key validation with permission check
            $key = $this->requireApiKey();
            if (!$key) {
                throw new Exception('API key not authorized', 401);
            }

            $this->requireDeletePermission($key);

            // Delegate to TicketService
            return $this->ticketService->deleteTicket($ticketNumber);
        } catch (Exception $e) {
            // Re-throw known exceptions (401, 404) without wrapping
            if (in_array($e->getCode(), [401, 404], true)) {
                throw $e;
            }

            error_log('[API-ENDPOINTS-ERROR] Delete failed for ticket ' . $ticketNumber . ': ' . $e->getMessage());
            throw new Exception('Failed to delete ticket: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get comprehensive ticket statistics
     *
     * @return array Statistics data structure
     * @throws Exception with code 401 if API key not authorized
     * @throws Exception with code 500 if stats aggregation fails
     */
    public function getTicketStats(): array
    {
        try {
            $key = $this->requireApiKey();
            if (!$key) {
                throw new Exception('API key not authorized', 401);
            }
            $this->requireStatsPermission($key);

            // Delegate to TicketService
            return $this->ticketService->getTicketStats();
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                throw $e;
            }

            error_log('[API-ENDPOINTS-ERROR] Stats aggregation failed: ' . $e->getMessage());
            throw new Exception('Failed to retrieve ticket statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search tickets with filters, pagination and sorting
     *
     * @param array $params Search parameters
     * @return array Array of tickets (without thread entries for performance)
     * @throws Exception if permission denied
     */
    public function searchTickets(array $params): array
    {
        // Get API key and check SEARCH permission
        $key = $this->requireApiKey();
        $this->requireSearchPermission($key);

        // Delegate to TicketService
        return $this->ticketService->searchTickets($params);
    }

    /**
     * Get all ticket statuses from database
     *
     * @return array Array of status objects sorted by sort order
     * @throws Exception with code 401 if API key not authorized
     * @throws Exception with code 500 if database query fails
     */
    public function getTicketStatuses(): array
    {
        try {
            $key = $this->requireApiKey();
            if (!$key) {
                throw new Exception('API key not authorized', 401);
            }
            $this->requireStatsPermission($key);

            // Delegate to TicketService
            return $this->ticketService->getTicketStatuses();
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                throw $e;
            }

            error_log('[API-ENDPOINTS-ERROR] Status lookup failed: ' . $e->getMessage());
            throw new Exception('Failed to retrieve ticket statuses: ' . $e->getMessage(), 500);
        }
    }
}
