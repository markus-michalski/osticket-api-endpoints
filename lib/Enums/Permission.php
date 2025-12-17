<?php
/**
 * Permission Enum
 *
 * Defines API key permission types for the extended API endpoints.
 * Maps to database columns in ost_api_key table.
 */

declare(strict_types=1);

enum Permission: string
{
    case CreateTickets = 'can_create_tickets';
    case UpdateTickets = 'can_update_tickets';
    case ReadTickets = 'can_read_tickets';
    case SearchTickets = 'can_search_tickets';
    case DeleteTickets = 'can_delete_tickets';
    case ReadStats = 'can_read_stats';
    case ManageSubtickets = 'can_manage_subtickets';

    /**
     * Get human-readable label for this permission
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::CreateTickets => 'Create Tickets',
            self::UpdateTickets => 'Update Tickets',
            self::ReadTickets => 'Read Tickets',
            self::SearchTickets => 'Search Tickets',
            self::DeleteTickets => 'Delete Tickets',
            self::ReadStats => 'Read Statistics',
            self::ManageSubtickets => 'Manage Subtickets',
        };
    }

    /**
     * Get associated API endpoint description
     *
     * @return string
     */
    public function endpoint(): string
    {
        return match ($this) {
            self::CreateTickets => 'POST /tickets',
            self::UpdateTickets => 'PATCH /tickets/:id',
            self::ReadTickets => 'GET /tickets/:number',
            self::SearchTickets => 'GET /tickets/search',
            self::DeleteTickets => 'DELETE /tickets/:number',
            self::ReadStats => 'GET /tickets-stats',
            self::ManageSubtickets => 'Subticket Operations',
        };
    }

    /**
     * Get error message for unauthorized access
     *
     * @param string $context Additional context (e.g., "tickets", "statistics")
     * @return string
     */
    public function unauthorizedMessage(string $context = 'this operation'): string
    {
        return sprintf('API key not authorized to %s', match ($this) {
            self::CreateTickets => 'create tickets',
            self::UpdateTickets => 'update tickets',
            self::ReadTickets => "read {$context}",
            self::SearchTickets => 'search tickets',
            self::DeleteTickets => 'delete tickets',
            self::ReadStats => 'read ticket statistics',
            self::ManageSubtickets => 'manage subtickets',
        });
    }

    /**
     * Get all permission column names
     *
     * @return array<string>
     */
    public static function columnNames(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get fallback permission (if any)
     *
     * For some permissions, a more general permission may grant access.
     * This matches the original fallback behavior in the controller:
     * - SearchTickets falls back to ReadTickets
     * - ReadStats falls back to ReadTickets
     *
     * @return self|null
     */
    public function fallback(): ?self
    {
        return match ($this) {
            self::SearchTickets => self::ReadTickets,
            self::ReadStats => self::ReadTickets,
            default => null,
        };
    }
}
