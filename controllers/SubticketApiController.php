<?php

require_once __DIR__ . '/ExtendedTicketApiController.php';

/**
 * Subticket API Controller
 *
 * GREEN PHASE: Minimal implementation for permission checks and plugin detection
 *
 * Handles Subticket-specific operations with permission checks:
 * - Permission check: can_manage_subtickets
 * - Plugin detection: subticket-manager plugin availability
 */
class SubticketApiController extends ExtendedTicketApiController {

    /**
     * @var API|null Override API key for testing
     */
    private $testApiKey = null;

    /**
     * Set API key for testing (bypasses requireApiKey())
     *
     * SECURITY WARNING: This method should ONLY be used in test environment!
     * It bypasses normal API key validation and should be disabled in production.
     *
     * @param API $key API key object
     * @throws Exception if called in production environment
     */
    public function setTestApiKey(API $key): void {
        // Prevent test key bypass in production
        if (defined('OSTICKET_PRODUCTION') && OSTICKET_PRODUCTION) {
            throw new Exception('Test API key not allowed in production', 403);
        }

        $this->testApiKey = $key;
    }

    /**
     * Get API key - use test key if set, otherwise call requireApiKey()
     *
     * @return API|null API key object
     */
    protected function getApiKey() {
        if ($this->testApiKey !== null) {
            return $this->testApiKey;
        }
        return $this->requireApiKey();
    }

    /**
     * Check if API key has subticket permission
     *
     * @return bool True if permission granted
     */
    public function hasSubticketPermission(): bool {
        // Get API key
        $key = $this->getApiKey();

        if (!$key) {
            return false;
        }

        // Check in ht array (osTicket's API class stores data in ht array)
        return isset($key->ht['can_manage_subtickets']) && $key->ht['can_manage_subtickets'];
    }

    /**
     * Require subticket permission or throw Exception
     *
     * @throws Exception if not authorized (403)
     * @return void
     */
    public function requireSubticketPermission(): void {
        if (!$this->hasSubticketPermission()) {
            throw new Exception('API key not authorized for subticket operations', 403);
        }
    }

    /**
     * Get ticket status, handling fallback to ID lookup
     *
     * @param Ticket $ticket The ticket to get status from
     * @return TicketStatus|null The ticket status object or null
     */
    private function getTicketStatus(Ticket $ticket): ?TicketStatus {
        $status = $ticket->getStatus();
        if (!$status) {
            $statusId = $ticket->getStatusId();
            if ($statusId !== null) {
                $status = TicketStatus::lookup($statusId);
            }
        }
        return $status;
    }

    /**
     * Check if API key can access ticket (Department-Level Authorization)
     *
     * Validates if the API key has access to the department of the given ticket.
     * If the API key has no department restrictions, access is granted to all departments.
     *
     * @param Ticket $ticket The ticket to check access for
     * @return bool True if access granted, false otherwise
     */
    private function canAccessTicket(Ticket $ticket): bool {
        $apiKey = $this->getApiKey();

        // If no API key, deny access
        if (!$apiKey) {
            return false;
        }

        // Get ticket's department ID
        $ticketDeptId = $ticket->getDeptId();

        // If ticket has no department, allow access (edge case)
        if (!$ticketDeptId) {
            return true;
        }

        // Check if API key has department restrictions
        if (method_exists($apiKey, 'hasRestrictedDepartments') && $apiKey->hasRestrictedDepartments()) {
            $allowedDepartments = $apiKey->getRestrictedDepartments();
            return in_array($ticketDeptId, $allowedDepartments, true); // Strict comparison for type safety
        }

        // No restrictions = full access
        return true;
    }

    /**
     * Get parent ticket of a subticket
     *
     * @param string $childNumber Child ticket number
     * @return array Parent ticket data or null
     * @throws Exception 400 - Invalid ticket number
     * @throws Exception 403 - API key not authorized for subticket management
     * @throws Exception 404 - Child ticket not found
     */
    public function getParent(string $childNumber): array {
        // 1. Validate ticket number
        if (empty($childNumber) || $childNumber === '0') {
            throw new Exception('Invalid ticket number', 400);
        }

        // 2. Permission check
        $this->requireSubticketPermission();

        // 3. Plugin availability check (before ticket lookup)
        // Note: User is responsible for having the plugin installed
        $plugin = $this->getSubticketPlugin();

        // 4. Child ticket lookup by number
        $childTicket = Ticket::lookupByNumber($childNumber);
        if (!$childTicket) {
            throw new Exception('Child ticket not found', 404);
        }

        // 5. Get parent via subticket plugin

        try {
            $parentData = $plugin->getParent($childTicket->getId());
        } catch (Exception $e) {
            // Plugin may throw exception if no parent exists
            // Treat as "no parent" instead of error
            $parentData = null;
        }

        // 5. Return result
        if (!$parentData) {
            return ['parent' => null];
        }

        // 6. Format parent ticket data - getParent returns array with keys:
        // ticket_id, number, subject, status
        return [
            'parent' => [
                'ticket_id' => (int)$parentData['ticket_id'],
                'number' => $parentData['number'],
                'subject' => $parentData['subject'],
                'status' => $parentData['status']
            ]
        ];
    }

    /**
     * Get list of child tickets for a parent ticket
     *
     * @param string $parentNumber Parent ticket number
     * @return array Array of child tickets
     * @throws Exception 400 - Invalid ticket number
     * @throws Exception 403 - API key not authorized for subticket management
     * @throws Exception 404 - Parent ticket not found
     */
    public function getList(string $parentNumber): array {
        // 1. Validate ticket number
        if (empty($parentNumber) || $parentNumber === '0') {
            throw new Exception('Invalid ticket number', 400);
        }

        // 2. Permission check
        $this->requireSubticketPermission();

        // 3. Plugin availability check (before ticket lookup)
        // Note: User is responsible for having the plugin installed
        $plugin = $this->getSubticketPlugin();

        // 4. Parent ticket lookup by number
        $parentTicket = Ticket::lookupByNumber($parentNumber);
        if (!$parentTicket) {
            throw new Exception('Parent ticket not found', 404);
        }

        // 5. Get children via subticket plugin
        $childrenData = $plugin->getChildren($parentTicket->getId());

        // 5. Return empty array if no children
        if (empty($childrenData)) {
            return ['children' => []];
        }

        // 6. Format children data - getChildren() returns array with keys:
        // id, number, subject, status, created
        // Convert 'id' to 'ticket_id' to match our API format
        $children = array_map(function($child) {
            return [
                'ticket_id' => (int)$child['id'],
                'number' => $child['number'],
                'subject' => $child['subject'],
                'status' => $child['status']
            ];
        }, $childrenData);

        return ['children' => $children];
    }

    /**
     * Create a subticket link between parent and child tickets
     *
     * @param string $parentNumber Parent ticket number
     * @param string $childNumber Child ticket number
     * @return array Success response with parent and child data
     * @throws Exception 400 - Invalid ticket numbers or self-link attempt
     * @throws Exception 403 - API key not authorized (permission or department access)
     * @throws Exception 404 - Parent or child ticket not found
     * @throws Exception 409 - Child already has a parent
     */
    public function createLink(string $parentNumber, string $childNumber): array {
        // 1. Validate parent ticket number
        if (empty($parentNumber) || $parentNumber === '0') {
            throw new Exception('Invalid parent ticket number', 400);
        }

        // 2. Validate child ticket number
        if (empty($childNumber) || $childNumber === '0') {
            throw new Exception('Invalid child ticket number', 400);
        }

        // 3. Check for self-link
        if ($parentNumber === $childNumber) {
            throw new Exception('Cannot link ticket to itself', 400);
        }

        // 4. Permission check
        $this->requireSubticketPermission();

        // 5. Plugin availability check (before ticket lookup)
        // Note: User is responsible for having the plugin installed
        $plugin = $this->getSubticketPlugin();

        // 6. Parent ticket lookup by number
        $parentTicket = Ticket::lookupByNumber($parentNumber);
        if (!$parentTicket) {
            throw new Exception('Parent ticket not found', 404);
        }

        // 7. Child ticket lookup by number
        $childTicket = Ticket::lookupByNumber($childNumber);
        if (!$childTicket) {
            throw new Exception('Child ticket not found', 404);
        }

        // 8. Department access check for parent ticket
        if (!$this->canAccessTicket($parentTicket)) {
            throw new Exception('Access denied to parent ticket department', 403);
        }

        // 9. Department access check for child ticket
        if (!$this->canAccessTicket($childTicket)) {
            throw new Exception('Access denied to child ticket department', 403);
        }

        // 10. Check if child already has a parent
        $existingParent = $plugin->getParent($childTicket->getId());

        if ($existingParent) {
            // Check if it's the same parent (relationship already exists)
            if ((int)$existingParent['ticket_id'] === $parentTicket->getId()) {
                // Note: Using HTTP 422 instead of 409 because osTicket's Http::response()
                // doesn't support 409 and falls back to 500. HTTP 422 is semantically
                // correct for "cannot process because duplicate relationship exists"
                throw new Exception('Subticket relationship already exists', 422);
            }
            // Different parent exists
            throw new Exception('Child ticket already has a different parent', 422);
        }

        // 10. Create the link using linkTicket method
        $success = $plugin->linkTicket($childTicket->getId(), $parentTicket->getId());

        if (!$success) {
            throw new Exception('Failed to create subticket relationship', 500);
        }

        // 11. Return success response with parent and child data
        return [
            'success' => true,
            'message' => 'Subticket relationship created successfully',
            'parent' => $this->formatTicketData($parentTicket),
            'child' => $this->formatTicketData($childTicket),
        ];
    }

    /**
     * Remove parent-child relationship (unlink subticket)
     *
     * @param string $childNumber Child ticket number
     * @return array Success response with child data
     * @throws Exception 400 - Invalid child ticket number
     * @throws Exception 403 - API key not authorized (permission or department access)
     * @throws Exception 404 - Child ticket not found or child has no parent
     */
    public function unlinkChild(string $childNumber): array {
        // 1. Validate child ticket number
        if (empty($childNumber) || $childNumber === '0') {
            throw new Exception('Invalid child ticket number', 400);
        }

        // 2. Permission check
        $this->requireSubticketPermission();

        // 3. Plugin availability check (before ticket lookup)
        // Note: User is responsible for having the plugin installed
        $plugin = $this->getSubticketPlugin();

        // 4. Child ticket lookup by number
        $childTicket = Ticket::lookupByNumber($childNumber);
        if (!$childTicket) {
            throw new Exception('Child ticket not found', 404);
        }

        // 5. Department access check for child ticket
        if (!$this->canAccessTicket($childTicket)) {
            throw new Exception('Access denied to child ticket department', 403);
        }

        // 6. Check if child has a parent

        $existingParent = $plugin->getParent($childTicket->getId());
        if (!$existingParent) {
            throw new Exception('Child has no parent to unlink', 404);
        }

        // 6. Remove the link using unlinkTicket method
        $success = $plugin->unlinkTicket($childTicket->getId());

        if (!$success) {
            throw new Exception('Failed to unlink subticket', 500);
        }

        // 7. Return success response with child data
        return [
            'success' => true,
            'message' => 'Subticket relationship removed successfully',
            'child' => $this->formatTicketData($childTicket),
        ];
    }

    /**
     * Get Subticket Manager Plugin instance
     *
     * @return SubticketPlugin Plugin instance
     * @throws Exception 500 - Subticket Manager Plugin not found or not loaded
     */
    private function getSubticketPlugin() {
        // Direct instantiation like in ajax-subticket.php
        if (!class_exists('SubticketPlugin')) {
            throw new Exception('Subticket plugin not available', 501);
        }

        return new SubticketPlugin();
    }

    /**
     * Check if Subticket Manager Plugin is available
     *
     * @return bool True if plugin is available and can be instantiated
     */
    public function isSubticketPluginAvailable(): bool {
        try {
            $this->getSubticketPlugin();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if API key can use subticket operations
     *
     * Combines permission check AND plugin availability check
     *
     * @return bool True if both conditions are met
     */
    public function canUseSubtickets(): bool {
        return $this->hasSubticketPermission() && $this->isSubticketPluginAvailable();
    }

    /**
     * Format ticket data for API response
     *
     * Formats a ticket object into standardized array structure
     * Used by both getParent() and getList() to ensure consistency
     *
     * @param Ticket $ticket Ticket to format
     * @return array Formatted ticket data with ticket_id, number, subject, status
     */
    private function formatTicketData(Ticket $ticket): array {
        $status = $this->getTicketStatus($ticket);

        return [
            'ticket_id' => (int)$ticket->getId(),
            'number' => $ticket->getNumber() ?? '',
            'subject' => $ticket->getSubject() ?? 'No Subject',
            'status' => $status ? $status->getName() : 'Unknown',
        ];
    }
}
