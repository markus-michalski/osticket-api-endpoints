<?php
/**
 * MessageFormat Enum
 *
 * Defines allowed message formats for ticket creation/updates.
 * Replaces magic strings like 'markdown', 'html', 'text'.
 *
 * NOTE: Named MessageFormat (not Format) to avoid conflict with osTicket's
 * built-in Format class in include/class.format.php
 */

declare(strict_types=1);

enum MessageFormat: string
{
    case Markdown = 'markdown';
    case Html = 'html';
    case Text = 'text';

    /**
     * Try to create Format from string (case-insensitive)
     *
     * @param string|null $value Input value
     * @return self|null Format instance or null if invalid
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return match (strtolower(trim($value))) {
            'markdown', 'md' => self::Markdown,
            'html' => self::Html,
            'text', 'plain', 'txt' => self::Text,
            default => null,
        };
    }

    /**
     * Get all allowed format values
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get comma-separated list of allowed formats (for error messages)
     *
     * @return string
     */
    public static function allowedList(): string
    {
        return implode(', ', self::values());
    }

    /**
     * Check if this format requires the Markdown plugin
     *
     * @return bool
     */
    public function requiresMarkdownPlugin(): bool
    {
        return $this === self::Markdown;
    }
}
