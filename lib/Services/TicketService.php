<?php
/**
 * Ticket Service
 *
 * Centralizes ticket CRUD operations and business logic.
 * Extracted from ExtendedTicketApiController to follow Single Responsibility Principle.
 *
 * Handles:
 * - Get single ticket with full details
 * - Delete ticket
 * - Get ticket statistics
 * - Search tickets with filters
 */

declare(strict_types=1);

class TicketService
{
    /**
     * Get ticket by number or ID with full details including thread/messages
     *
     * @param string|int $ticketIdentifier Ticket number (e.g. ABC-123-456) or ID
     * @return array Ticket data with all messages
     * @throws Exception if ticket not found (404)
     */
    public function getTicket(string|int $ticketIdentifier): array
    {
        // Load ticket - try by number first (user provides ticket NUMBER like "781258")
        $ticket = Ticket::lookupByNumber((string)$ticketIdentifier);

        // If not found by number, try by ID as fallback
        if (!$ticket) {
            $ticket = Ticket::lookup($ticketIdentifier);
        }

        if (!$ticket) {
            throw new Exception('Ticket not found', 404);
        }

        return $this->buildTicketResponse($ticket);
    }

    /**
     * Build full ticket response array
     */
    private function buildTicketResponse(Ticket $ticket): array
    {
        $response = [
            'id' => $ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'statusId' => $ticket->getStatusId(),
            'status' => (string)$ticket->getStatus(),
            'priorityId' => $ticket->getPriorityId(),
            'priority' => (string)$ticket->getPriority(),
            'departmentId' => $ticket->getDeptId(),
            'department' => $ticket->getDept()?->getName(),
            'topicId' => $ticket->getTopicId(),
            'topic' => $ticket->getTopic()?->getName(),
            'userId' => $ticket->getUserId(),
            'user' => [
                'name' => $ticket->getName(),
                'email' => $ticket->getEmail()
            ],
            'staffId' => $ticket->getStaffId(),
            'staff' => $ticket->getStaff()?->getName(),
            'teamId' => $ticket->getTeamId(),
            'team' => $ticket->getTeam()?->getName(),
            'slaId' => $ticket->getSLAId(),
            'sla' => $ticket->getSLA()?->getName(),
            'created' => $ticket->getCreateDate(),
            'updated' => $ticket->getUpdateDate(),
            'duedate' => $ticket->getDueDate(),
            'closed' => $ticket->isClosed() ? $ticket->getCloseDate() : null,
            'isOverdue' => $ticket->isOverdue(),
            'isAnswered' => $ticket->isAnswered(),
            'source' => $ticket->getSource(),
            'ip' => $ticket->getIP(),
            'children' => $this->getChildTicketIds($ticket),
            'thread' => $this->getThreadEntries($ticket)
        ];

        return $response;
    }

    /**
     * Get child ticket IDs for a parent ticket
     */
    private function getChildTicketIds(Ticket $ticket): array
    {
        $children = [];
        $ticketId = $ticket->getId();

        // Only query database if we're in production environment
        if (defined('TICKET_TABLE')) {
            $sql = sprintf(
                "SELECT ticket_id FROM %s WHERE ticket_pid = %d",
                TICKET_TABLE,
                (int)$ticketId
            );
            $result = db_query($sql);
            if ($result) {
                while ($row = db_fetch_array($result)) {
                    $children[] = (int)$row['ticket_id'];
                }
            }
        } else {
            // In test environment, get children from ticket object if method exists
            if (method_exists($ticket, 'getChildren')) {
                foreach ($ticket->getChildren() as $childId) {
                    $children[] = (int)$childId;
                }
            }
        }

        return $children;
    }

    /**
     * Get thread entries for a ticket
     */
    private function getThreadEntries(Ticket $ticket): array
    {
        $entries = [];
        $thread = $ticket->getThread();

        if (!$thread) {
            return $entries;
        }

        foreach ($thread->getEntries() as $entry) {
            $threadEntry = [
                'id' => $entry->getId(),
                'type' => $entry->getType(),
                'poster' => $entry->getPoster(),
                'timestamp' => $entry->getCreateDate(),
                'body' => $entry->getBody()
            ];

            // Add staff info if it's an internal note or response
            if ($entry->getStaffId()) {
                $threadEntry['staffId'] = $entry->getStaffId();
                $threadEntry['staff'] = $entry->getStaff()?->getName();
            }

            // Add user info if it's a user message
            if ($entry->getUserId()) {
                $threadEntry['userId'] = $entry->getUserId();
            }

            $entries[] = $threadEntry;
        }

        return $entries;
    }

    /**
     * Delete a ticket and all associated data
     *
     * @param string|int $ticketIdentifier Ticket number or internal ticket ID
     * @return string Deleted ticket number
     * @throws Exception 404 if ticket not found, 500 if deletion fails
     */
    public function deleteTicket(string|int $ticketIdentifier): string
    {
        // Lookup ticket by number first
        $ticket = Ticket::lookupByNumber((string)$ticketIdentifier);

        // Fallback to ID lookup
        if (!$ticket) {
            $ticket = Ticket::lookup($ticketIdentifier);
        }

        if (!$ticket) {
            throw new Exception('Ticket not found', 404);
        }

        // Store ticket data BEFORE deletion (object will be destroyed)
        $ticketNumberToReturn = $ticket->getNumber();

        // Delete ticket using osTicket's delete() method
        $ticket->delete();

        return $ticketNumberToReturn;
    }

    /**
     * Get comprehensive ticket statistics
     *
     * @return array Statistics data structure
     */
    public function getTicketStats(): array
    {
        // Fetch all tickets
        $tickets = Ticket::objects();

        // Initialize global stats
        $stats = [
            'total' => 0,
            'open' => 0,
            'closed' => 0,
            'overdue' => 0,
            'by_department' => [],
            'by_staff' => []
        ];

        // Data structures for aggregation
        $deptStats = [];
        $staffStats = [];

        // Iterate through all tickets and aggregate stats
        foreach ($tickets as $ticket) {
            $stats['total']++;

            $isClosed = $ticket->isClosed();

            if ($isClosed) {
                $stats['closed']++;
            } else {
                $stats['open']++;
            }

            if ($ticket->isOverdue()) {
                $stats['overdue']++;
            }

            // Department stats
            $dept = $ticket->getDept();
            if ($dept) {
                $deptName = $dept->getName();
                if (!isset($deptStats[$deptName])) {
                    $deptStats[$deptName] = ['total' => 0, 'open' => 0, 'closed' => 0, 'overdue' => 0];
                }
                $deptStats[$deptName]['total']++;
                if ($isClosed) {
                    $deptStats[$deptName]['closed']++;
                } else {
                    $deptStats[$deptName]['open']++;
                }
                if ($ticket->isOverdue()) {
                    $deptStats[$deptName]['overdue']++;
                }
            }

            // Staff stats
            if ($ticket->getStaffId() && $ticket->getStaff()) {
                $staffId = $ticket->getStaffId();
                if (!isset($staffStats[$staffId])) {
                    $staffStats[$staffId] = [
                        'staff_id' => $staffId,
                        'staff_name' => $ticket->getStaff()->getName(),
                        'total' => 0,
                        'departments' => []
                    ];
                }
                $staffStats[$staffId]['total']++;

                // Department breakdown for this staff member
                if ($dept) {
                    $deptName = $dept->getName();
                    if (!isset($staffStats[$staffId]['departments'][$deptName])) {
                        $staffStats[$staffId]['departments'][$deptName] = [
                            'open' => 0,
                            'closed' => 0,
                            'overdue' => 0
                        ];
                    }
                    if ($isClosed) {
                        $staffStats[$staffId]['departments'][$deptName]['closed']++;
                    } else {
                        $staffStats[$staffId]['departments'][$deptName]['open']++;
                    }
                    if ($ticket->isOverdue()) {
                        $staffStats[$staffId]['departments'][$deptName]['overdue']++;
                    }
                }
            }
        }

        // Sort departments alphabetically
        ksort($deptStats);
        $stats['by_department'] = $deptStats;

        // Sort staff by name and convert to indexed array
        usort($staffStats, fn($a, $b) => strcmp($a['staff_name'], $b['staff_name']));
        $stats['by_staff'] = array_values($staffStats);

        return $stats;
    }

    /**
     * Search tickets with filters, pagination and sorting
     *
     * @param array $params Search parameters
     * @return array Array of tickets (without thread entries for performance)
     */
    public function searchTickets(array $params): array
    {
        $query = isset($params['query']) ? trim($params['query']) : null;

        // Status filter
        $statusFilter = $this->resolveStatusFilter($params['status'] ?? null);

        // Department filter
        $deptFilter = $this->resolveDepartmentFilter($params['department'] ?? null);

        // Pagination
        $limit = $this->resolvePaginationLimit($params['limit'] ?? null);
        $offset = max(0, (int)($params['offset'] ?? 0));

        // Sorting
        $sort = $this->resolveSortField($params['sort'] ?? null);

        // Build database query
        $tickets = Ticket::objects();

        // Filter by query (search in subject)
        if ($query !== null && $query !== '') {
            $tickets = $tickets->filter(['cdata__subject__contains' => $query]);
        }

        // Filter by status
        if ($statusFilter !== null) {
            $tickets = $tickets->filter(['status_id' => $statusFilter]);
        }

        // Filter by department
        if ($deptFilter !== null) {
            $tickets = $tickets->filter(['dept_id' => $deptFilter]);
        }

        // Apply sorting
        $tickets = match ($sort) {
            'updated' => $tickets->order_by('-updated'),
            'number' => $tickets->order_by('number'),
            default => $tickets->order_by('-created'),
        };

        // Apply pagination
        $tickets = $tickets->limit($limit)->offset($offset);

        // Build response array
        return array_map(
            fn($ticket) => $this->buildSearchResultItem($ticket),
            iterator_to_array($tickets)
        );
    }

    /**
     * Resolve status filter from parameter
     */
    private function resolveStatusFilter(mixed $status): ?int
    {
        if ($status === null) {
            return null;
        }

        $statusParam = is_string($status) ? trim($status) : $status;

        if (is_numeric($statusParam)) {
            return (int)$statusParam;
        }

        // String: lookup status by name
        if (defined('TICKET_STATUS_TABLE')) {
            $sql = sprintf(
                "SELECT id FROM %s WHERE LOWER(name) = '%s'",
                TICKET_STATUS_TABLE,
                db_real_escape(strtolower((string)$statusParam))
            );
            $result = db_query($sql);
            if ($result && ($row = db_fetch_array($result))) {
                return (int)$row['id'];
            }
        }

        return null;
    }

    /**
     * Resolve department filter from parameter
     */
    private function resolveDepartmentFilter(mixed $department): ?int
    {
        if ($department === null) {
            return null;
        }

        $deptParam = is_string($department) ? trim($department) : $department;

        if (is_numeric($deptParam)) {
            return (int)$deptParam;
        }

        if (!is_string($deptParam)) {
            return null;
        }

        // Path format: "Development / osTicket"
        if (strpos($deptParam, '/') !== false) {
            return $this->resolveDepartmentPath($deptParam);
        }

        // Simple name lookup
        return $this->resolveDepartmentByName($deptParam);
    }

    /**
     * Resolve department from path format
     */
    private function resolveDepartmentPath(string $path): ?int
    {
        if (!defined('DEPT_TABLE')) {
            return null;
        }

        $parts = array_map('trim', explode('/', $path));
        $currentDeptId = null;

        foreach ($parts as $index => $partName) {
            if ($index === 0) {
                $sql = sprintf(
                    "SELECT id FROM %s WHERE LOWER(name) = '%s' AND pid IS NULL",
                    DEPT_TABLE,
                    db_real_escape(strtolower($partName))
                );
            } else {
                $sql = sprintf(
                    "SELECT id FROM %s WHERE LOWER(name) = '%s' AND pid = %d",
                    DEPT_TABLE,
                    db_real_escape(strtolower($partName)),
                    $currentDeptId
                );
            }

            $result = db_query($sql);
            if ($result && ($row = db_fetch_array($result))) {
                $currentDeptId = (int)$row['id'];
            } else {
                return null;
            }
        }

        return $currentDeptId;
    }

    /**
     * Resolve department by name
     */
    private function resolveDepartmentByName(string $name): ?int
    {
        if (!defined('DEPT_TABLE')) {
            return null;
        }

        $sql = sprintf(
            "SELECT id FROM %s WHERE LOWER(name) = '%s' LIMIT 1",
            DEPT_TABLE,
            db_real_escape(strtolower($name))
        );
        $result = db_query($sql);

        if ($result && ($row = db_fetch_array($result))) {
            return (int)$row['id'];
        }

        return null;
    }

    /**
     * Resolve pagination limit
     *
     * Invalid values (null, negative, zero) default to 20
     * Values above 100 are capped at 100
     */
    private function resolvePaginationLimit(mixed $limit): int
    {
        if ($limit === null) {
            return 20;
        }

        $value = (int)$limit;

        // Negative or zero defaults to 20
        if ($value < 1) {
            return 20;
        }

        // Cap at 100
        return min($value, 100);
    }

    /**
     * Resolve sort field
     */
    private function resolveSortField(?string $sort): string
    {
        $allowedSorts = ['created', 'updated', 'number'];
        $sortValue = strtolower(trim($sort ?? 'created'));
        return in_array($sortValue, $allowedSorts, true) ? $sortValue : 'created';
    }

    /**
     * Build search result item
     */
    private function buildSearchResultItem(Ticket $ticket): array
    {
        return [
            'id' => $ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'statusId' => $ticket->getStatusId(),
            'status' => (string)$ticket->getStatus(),
            'priorityId' => $ticket->getPriorityId(),
            'priority' => (string)$ticket->getPriority(),
            'departmentId' => $ticket->getDeptId(),
            'department' => $ticket->getDept()?->getName(),
            'topicId' => $ticket->getTopicId(),
            'topic' => $ticket->getTopic()?->getName(),
            'created' => $ticket->getCreateDate(),
            'updated' => $ticket->getUpdateDate(),
            'dueDate' => $ticket->getDueDate(),
            'staffId' => $ticket->getStaffId(),
            'staff' => $ticket->getStaff()?->getName(),
            'teamId' => $ticket->getTeamId(),
            'team' => $ticket->getTeam()?->getName(),
            'slaId' => $ticket->getSLAId(),
            'sla' => $ticket->getSLA()?->getName(),
            'isOverdue' => $ticket->isOverdue(),
            'isAnswered' => $ticket->isAnswered()
        ];
    }

    /**
     * Get all ticket statuses
     *
     * @return array Array of status objects
     */
    public function getTicketStatuses(): array
    {
        $statuses = TicketStatus::objects()
            ->order_by('sort')
            ->all();

        $results = [];
        foreach ($statuses as $status) {
            $results[] = [
                'id' => $status->getId(),
                'name' => $status->getName(),
                'state' => $status->getState()
            ];
        }

        return $results;
    }

    // =========================================================================
    // Singleton Pattern
    // =========================================================================

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
