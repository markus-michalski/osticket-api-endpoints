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
     * Check if Subticket plugin is available and active
     *
     * @return bool True if plugin available
     */
    public function isSubticketPluginAvailable(): bool {
        // Get plugin via PluginManager
        $plugin = PluginManager::getInstance()->getPlugin('subticket');

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
}
