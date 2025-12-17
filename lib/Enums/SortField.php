<?php
/**
 * SortField Enum
 *
 * Defines allowed sort fields for ticket search/listing.
 */

declare(strict_types=1);

enum SortField: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Number = 'number';

    /**
     * Get the order_by value for osTicket's QuerySet
     *
     * @param bool $descending Whether to sort descending (default: true for dates)
     * @return string
     */
    public function orderBy(bool $descending = true): string
    {
        $prefix = $descending ? '-' : '';

        return match ($this) {
            self::Created => $prefix . 'created',
            self::Updated => $prefix . 'updated',
            self::Number => 'number', // Number typically sorted ascending
        };
    }

    /**
     * Try to create SortField from string
     *
     * @param string|null $value Input value
     * @return self Default to Created if invalid
     */
    public static function fromString(?string $value): self
    {
        if ($value === null) {
            return self::Created;
        }

        return match (strtolower(trim($value))) {
            'updated', 'update', 'modified' => self::Updated,
            'number', 'id', 'ticket_number' => self::Number,
            default => self::Created,
        };
    }

    /**
     * Get all allowed sort values
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
