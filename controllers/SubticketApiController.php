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
     * @var Plugin|null Cached subticket plugin instance
     */
    private $subticketPluginCache = null;

    /**
     * Set API key for testing (bypasses requireApiKey())
     *
     * @param API $key API key object
     */
    public function setTestApiKey($key) {
        $this->testApiKey = $key;
    }

    /**
     * Get API key - use test key if set, otherwise call requireApiKey()
     *
     * @return API|null API key object
     */
    private function getApiKey() {
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
     * Get cached subticket plugin instance
     *
     * @return object|null Subticket plugin instance or null
     */
    private function getSubticketPlugin() {
        if ($this->subticketPluginCache === null) {
            $this->subticketPluginCache = PluginManager::getInstance()->getPlugin('subticket');
        }
        return $this->subticketPluginCache;
    }

    /**
     * Check if Subticket plugin is available and active
     *
     * @return bool True if plugin available
     */
    public function isSubticketPluginAvailable(): bool {
        // Get plugin via cached method
        $plugin = $this->getSubticketPlugin();

        if (!$plugin) {
            return false;
        }

        // Check if plugin is active
        if (method_exists($plugin, 'isActive')) {
            return $plugin->isActive();
        }

        return false;
    }

    /**
     * Combined check: Permission AND Plugin available
     *
     * @return bool True if both conditions met
     */
    public function canUseSubtickets(): bool {
        return $this->hasSubticketPermission() && $this->isSubticketPluginAvailable();
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
        // In real osTicket, this would be: $apiKey->getDepartments()
        // For now, we assume API keys without explicit restrictions have full access
        // This can be extended when department restrictions are added to API keys

        // NOTE: In production osTicket, implement proper department access check here
        // Example: return in_array($ticketDeptId, $apiKey->getDepartments());

        return true; // Allow access for now (TODO: Implement proper department restrictions)
    }

    /**
     * Get parent ticket of a subticket
     *
     * @param int $childId Child ticket ID
     * @return array Parent ticket data or null
     * @throws Exception 400 - Invalid ticket ID (ID must be > 0)
     * @throws Exception 403 - API key not authorized for subticket management
     * @throws Exception 404 - Child ticket not found
     * @throws Exception 501 - Subticket Manager Plugin not available
     */
    public function getParent(int $childId): array {
        // 1. Validate ticket ID
        if ($childId <= 0) {
            throw new Exception('Invalid ticket ID', 400);
        }

        // 2. Permission check
        $this->requireSubticketPermission();

        // 3. Plugin check
        if (!$this->isSubticketPluginAvailable()) {
            throw new Exception('Subticket plugin not available', 501);
        }

        // 4. Child ticket lookup
        $childTicket = Ticket::lookup($childId);
        if (!$childTicket) {
            throw new Exception('Ticket not found', 404);
        }

        // 5. Get parent via cached plugin instance
        $plugin = $this->getSubticketPlugin();
        $parentTicket = $plugin->getParent($childTicket);

        // 6. Return result
        if (!$parentTicket) {
            return ['parent' => null];
        }

        // 7. Format parent ticket data using helper method
        return [
            'parent' => $this->formatTicketData($parentTicket)
        ];
    }

    /**
     * Get list of child tickets for a parent ticket
     *
     * @param int $parentId Parent ticket ID
     * @return array Array of child tickets
     * @throws Exception 400 - Invalid ticket ID (ID must be > 0)
     * @throws Exception 403 - API key not authorized for subticket management
     * @throws Exception 404 - Parent ticket not found
     * @throws Exception 501 - Subticket Manager Plugin not available
     */
    public function getList(int $parentId): array {
        // 1. Validate ticket ID
        if ($parentId <= 0) {
            throw new Exception('Invalid ticket ID', 400);
        }

        // 2. Permission check
        $this->requireSubticketPermission();

        // 3. Plugin check
        if (!$this->isSubticketPluginAvailable()) {
            throw new Exception('Subticket plugin not available', 501);
        }

        // 4. Parent ticket lookup
        $parentTicket = Ticket::lookup($parentId);
        if (!$parentTicket) {
            throw new Exception('Ticket not found', 404);
        }

        // 5. Get children via cached plugin instance
        $plugin = $this->getSubticketPlugin();
        $childIds = $plugin->getChildren($parentTicket);

        // 6. Return empty array if no children
        if (empty($childIds)) {
            return ['children' => []];
        }

        // 7. Iterate over child IDs and build result array
        $children = [];
        foreach ($childIds as $childId) {
            // Lookup child ticket
            $childTicket = Ticket::lookup($childId);

            // Skip if child ticket doesn't exist (orphaned reference)
            if (!$childTicket) {
                continue;
            }

            // Format ticket data using helper method
            $children[] = $this->formatTicketData($childTicket);
        }

        return ['children' => $children];
    }

    /**
     * Create a subticket link between parent and child tickets
     *
     * @param int $parentId Parent ticket ID
     * @param int $childId Child ticket ID
     * @return array Success response with parent and child data
     * @throws Exception 400 - Invalid ticket IDs or self-link attempt
     * @throws Exception 403 - API key not authorized (permission or department access)
     * @throws Exception 404 - Parent or child ticket not found
     * @throws Exception 409 - Child already has a parent
     * @throws Exception 501 - Subticket Manager Plugin not available
     */
    public function createLink(int $parentId, int $childId): array {
        // 1. Validate parent ticket ID
        if ($parentId <= 0) {
            throw new Exception('Invalid parent ticket ID', 400);
        }

        // 2. Validate child ticket ID
        if ($childId <= 0) {
            throw new Exception('Invalid child ticket ID', 400);
        }

        // 3. Check for self-link
        if ($parentId === $childId) {
            throw new Exception('Cannot link ticket to itself', 400);
        }

        // 4. Permission check
        $this->requireSubticketPermission();

        // 5. Plugin check
        if (!$this->isSubticketPluginAvailable()) {
            throw new Exception('Subticket plugin not available', 501);
        }

        // 6. Parent ticket lookup
        $parentTicket = Ticket::lookup($parentId);
        if (!$parentTicket) {
            throw new Exception('Parent ticket not found', 404);
        }

        // 7. Child ticket lookup
        $childTicket = Ticket::lookup($childId);
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
        $plugin = $this->getSubticketPlugin();
        $existingParent = $plugin->getParent($childTicket);

        if ($existingParent) {
            // Check if it's the same parent (relationship already exists)
            if ($existingParent->getId() === $parentId) {
                throw new Exception('Subticket relationship already exists', 409);
            }
            // Different parent exists
            throw new Exception('Child ticket already has a different parent', 409);
        }

        // 11. Create the link
        $plugin->createLink($parentTicket, $childTicket);

        // 12. Return success response with parent and child data
        return [
            'success' => true,
            'message' => 'Subticket relationship created successfully',
            'parent' => $this->formatTicketData($parentTicket),
            'child' => $this->formatTicketData($childTicket),
        ];
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
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'status' => $status ? $status->getName() : 'Unknown',
        ];
    }
}
