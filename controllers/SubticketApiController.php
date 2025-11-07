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
        $status = $this->getTicketStatus($parentTicket);

        return [
            'parent' => [
                'ticket_id' => (int)$parentTicket->getId(),
                'number' => $parentTicket->getNumber(),
                'subject' => $parentTicket->getSubject(),
                'status' => $status ? $status->getName() : 'Unknown',
            ]
        ];
    }
}
