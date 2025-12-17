<?php
/**
 * Permission Checker Service
 *
 * Centralizes API key permission checking logic.
 * Extracted from ExtendedTicketApiController to improve separation of concerns.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Enums/Permission.php';

class PermissionChecker
{
    /**
     * Check if API key has a specific permission
     *
     * @param API $apiKey The API key object
     * @param Permission $permission The permission to check
     * @return bool True if permission granted
     */
    public function has(API $apiKey, Permission $permission): bool
    {
        $column = $permission->value;

        // Check in ht array (osTicket's API class stores data in ht array)
        if (isset($apiKey->ht[$column]) && $apiKey->ht[$column]) {
            return true;
        }

        // Check fallback permission if defined
        $fallback = $permission->fallback();
        if ($fallback !== null) {
            return $this->has($apiKey, $fallback);
        }

        return false;
    }

    /**
     * Require permission or throw exception
     *
     * @param API $apiKey The API key object
     * @param Permission $permission The required permission
     * @param string $context Additional context for error message
     * @throws Exception If permission not granted (401)
     */
    public function require(API $apiKey, Permission $permission, string $context = ''): void
    {
        if (!$this->has($apiKey, $permission)) {
            $message = $context !== ''
                ? $permission->unauthorizedMessage($context)
                : $permission->unauthorizedMessage();

            throw new Exception($message, 401);
        }
    }

    /**
     * Check if API key has any of the given permissions
     *
     * @param API $apiKey The API key object
     * @param Permission ...$permissions Permissions to check (OR logic)
     * @return bool True if at least one permission granted
     */
    public function hasAny(API $apiKey, Permission ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->has($apiKey, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if API key has all of the given permissions
     *
     * @param API $apiKey The API key object
     * @param Permission ...$permissions Permissions to check (AND logic)
     * @return bool True if all permissions granted
     */
    public function hasAll(API $apiKey, Permission ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->has($apiKey, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get list of granted permissions for an API key
     *
     * @param API $apiKey The API key object
     * @return array<Permission> List of granted permissions
     */
    public function getGrantedPermissions(API $apiKey): array
    {
        $granted = [];

        foreach (Permission::cases() as $permission) {
            if ($this->has($apiKey, $permission)) {
                $granted[] = $permission;
            }
        }

        return $granted;
    }

    /**
     * Singleton instance for convenience
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
