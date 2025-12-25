<?php
/**
 * Ticket Validator Service
 *
 * Centralizes all validation logic for ticket-related entities.
 * Extracted from ExtendedTicketApiController to follow Single Responsibility Principle.
 *
 * Handles validation for:
 * - Format (markdown, html, text)
 * - Department ID/name
 * - Topic ID/name
 * - Status ID/name
 * - SLA ID/name
 * - Staff ID/username
 * - Parent ticket (for subtickets)
 */

declare(strict_types=1);

require_once __DIR__ . '/../Enums/MessageFormat.php';

class TicketValidatorService
{
    /**
     * Validate format parameter
     *
     * @param string|null $format Format to validate
     * @return string Validated and normalized format
     * @throws Exception If format is invalid (400)
     */
    public function validateFormat(?string $format): string
    {
        $formatEnum = MessageFormat::tryFromString($format);

        if ($formatEnum === null) {
            if ($format === null || trim($format) === '') {
                throw new Exception('Format cannot be empty', 400);
            }
            throw new Exception('Invalid format. Allowed: ' . MessageFormat::allowedList(), 400);
        }

        return $formatEnum->value;
    }

    /**
     * Validate department ID or name
     *
     * Accepts both ID (int) and name (string).
     * If name is provided, looks up ID by name (case-insensitive).
     *
     * @param int|string $deptId Department ID or name
     * @return int Validated department ID
     * @throws Exception If department not found (404) or inactive (400)
     */
    public function validateDepartmentId(int|string $deptId): int
    {
        // If string provided, try to lookup by name first
        if (is_string($deptId) && !is_numeric($deptId)) {
            $deptId = $this->resolveDepartmentByName($deptId);
        }

        // Now lookup by ID
        $dept = Dept::lookup($deptId);

        if (!$dept) {
            throw new Exception('Department not found', 404);
        }

        if (!$dept->isActive()) {
            throw new Exception('Department is not active', 400);
        }

        return (int)$deptId;
    }

    /**
     * Resolve department ID from name
     *
     * @param string $name Department name
     * @return int Resolved department ID
     * @throws Exception If department not found (404)
     */
    private function resolveDepartmentByName(string $name): int
    {
        // Try exact match first using osTicket's method
        $resolvedId = Dept::getIdByName($name);

        // If no exact match, try case-insensitive search
        if (!$resolvedId) {
            foreach (Dept::objects()->filter(['ispublic' => 1]) as $dept) {
                if (strcasecmp($dept->getName(), $name) === 0) {
                    return (int)$dept->getId();
                }
            }
        }

        if ($resolvedId) {
            return (int)$resolvedId;
        }

        throw new Exception("Department '{$name}' not found", 404);
    }

    /**
     * Validate topic (Help Topic) ID or name
     *
     * @param int|string $topicId Topic ID or name
     * @return int Validated topic ID
     * @throws Exception If topic not found (404) or inactive (400)
     */
    public function validateTopicId(int|string $topicId): int
    {
        // If string provided, try to lookup by name first
        if (is_string($topicId) && !is_numeric($topicId)) {
            $topicId = $this->resolveTopicByName($topicId);
        }

        // Now lookup by ID
        $topic = Topic::lookup($topicId);

        if (!$topic) {
            throw new Exception('Help Topic not found', 404);
        }

        if (!$topic->isActive()) {
            throw new Exception('Help Topic is not active', 400);
        }

        return (int)$topicId;
    }

    /**
     * Resolve topic ID from name
     *
     * @param string $name Topic name
     * @return int Resolved topic ID
     * @throws Exception If topic not found (404)
     */
    private function resolveTopicByName(string $name): int
    {
        // Try exact match first
        $resolvedId = Topic::getIdByName($name);

        // If no exact match, try case-insensitive search
        if (!$resolvedId) {
            foreach (Topic::objects()->filter(['isactive' => 1]) as $topic) {
                if (strcasecmp($topic->getName(), $name) === 0) {
                    return (int)$topic->getId();
                }
            }
        }

        if ($resolvedId) {
            return (int)$resolvedId;
        }

        throw new Exception("Help Topic '{$name}' not found", 404);
    }

    /**
     * Validate status ID or name
     *
     * @param int|string $statusId Status ID or name
     * @return int Validated status ID
     * @throws Exception If status not found (404)
     */
    public function validateStatusId(int|string $statusId): int
    {
        // If string provided, try to lookup by name first
        if (is_string($statusId) && !is_numeric($statusId)) {
            $statusId = $this->resolveStatusByName($statusId);
        }

        // Now lookup by ID
        $status = TicketStatus::lookup($statusId);

        if (!$status) {
            throw new Exception('Status not found', 404);
        }

        return (int)$statusId;
    }

    /**
     * Resolve status ID from name
     *
     * @param string $name Status name
     * @return int Resolved status ID
     * @throws Exception If status not found (404)
     */
    private function resolveStatusByName(string $name): int
    {
        foreach (TicketStatus::objects() as $status) {
            if (strcasecmp($status->getName(), $name) === 0) {
                return (int)$status->getId();
            }
        }

        throw new Exception("Status '{$name}' not found", 404);
    }

    /**
     * Validate SLA ID or name
     *
     * @param int|string $slaId SLA ID or name
     * @return int Validated SLA ID
     * @throws Exception If SLA not found (404) or inactive (400)
     */
    public function validateSlaId(int|string $slaId): int
    {
        // If string provided, try to lookup by name first
        if (is_string($slaId) && !is_numeric($slaId)) {
            $slaId = $this->resolveSlaByName($slaId);
        }

        // Now lookup by ID
        $sla = SLA::lookup($slaId);

        if (!$sla) {
            throw new Exception('SLA not found', 404);
        }

        if (!$sla->isActive()) {
            throw new Exception('SLA is not active', 400);
        }

        return (int)$slaId;
    }

    /**
     * Resolve SLA ID from name
     *
     * @param string $name SLA name
     * @return int Resolved SLA ID
     * @throws Exception If SLA not found (404)
     */
    private function resolveSlaByName(string $name): int
    {
        // Try exact match first
        $resolvedId = SLA::getIdByName($name);

        // If no exact match, try case-insensitive search
        if (!$resolvedId) {
            foreach (SLA::objects()->filter(['isactive' => 1]) as $sla) {
                if (strcasecmp($sla->getName(), $name) === 0) {
                    return (int)$sla->getId();
                }
            }
        }

        if ($resolvedId) {
            return (int)$resolvedId;
        }

        throw new Exception("SLA '{$name}' not found", 404);
    }

    /**
     * Validate staff ID or username
     *
     * Staff::lookup() already handles username, email, or ID.
     *
     * @param int|string $staffId Staff ID or username
     * @return int Validated staff ID
     * @throws Exception If staff not found (404) or inactive (400)
     */
    public function validateStaffId(int|string $staffId): int
    {
        // Staff::lookup() already handles username, email, or ID
        $staff = Staff::lookup($staffId);

        if (!$staff) {
            throw new Exception('Staff member not found', 404);
        }

        if (!$staff->isActive()) {
            throw new Exception('Staff member is not active', 400);
        }

        return (int)$staff->getId();
    }

    /**
     * Validate parent ticket for subticket creation
     *
     * @param int|string $parentId Parent ticket number or ID
     * @return int Validated parent ticket ID (internal ID)
     * @throws Exception If parent not found (404) or is itself a child (400)
     */
    public function validateParentTicketId(int|string $parentId): int
    {
        // Try to lookup by number first (user provides ticket NUMBER like "191215")
        $parent = Ticket::lookupByNumber((string)$parentId);

        // If not found by number, try by ID as fallback
        if (!$parent) {
            $parent = Ticket::lookup($parentId);
        }

        if (!$parent) {
            throw new Exception('Parent ticket not found', 404);
        }

        // Check if parent is not itself a child
        if (method_exists($parent, 'isChild') && $parent->isChild()) {
            throw new Exception('Parent ticket cannot be a child of another ticket', 400);
        }

        // Return the INTERNAL ID (ticket_id), not the number
        return (int)$parent->getId();
    }

    /**
     * Validate and parse due date
     *
     * Accepts various date formats:
     * - ISO 8601 date: "2025-01-31"
     * - ISO 8601 datetime: "2025-01-31T17:30:00"
     * - ISO 8601 with timezone: "2025-01-31T17:30:00+01:00"
     * - MySQL datetime: "2025-01-31 17:30:00"
     * - null to clear due date
     *
     * @param string|null $dueDate Date string or null
     * @return string|null Normalized MySQL datetime string or null
     * @throws Exception If date format is invalid (400)
     */
    public function validateDueDate(?string $dueDate): ?string
    {
        // null clears the due date
        if ($dueDate === null || trim($dueDate) === '') {
            return null;
        }

        // Try to parse the date
        try {
            $dateTime = new DateTime($dueDate);

            // Return in MySQL datetime format
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new Exception(
                'Invalid date format for dueDate. Expected ISO 8601 format (e.g., "2025-01-31" or "2025-01-31T17:30:00")',
                400
            );
        }
    }

    /**
     * Check if Markdown Support Plugin is active
     *
     * @return bool True if plugin is active
     */
    public function isMarkdownPluginActive(): bool
    {
        // Method 1: Check if MarkdownThreadEntryBody class exists (preferred, faster)
        if (class_exists('MarkdownThreadEntryBody')) {
            return true;
        }

        // Method 2: Check via PluginManager
        $markdownPlugin = PluginManager::getInstance()->getPlugin('markdown-support');

        return $markdownPlugin
            && method_exists($markdownPlugin, 'isActive')
            && $markdownPlugin->isActive();
    }

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
