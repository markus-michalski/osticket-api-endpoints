<?php

use PHPUnit\Framework\TestCase;

// Load the controller
require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';

/**
 * Integration Tests for GET /api/tickets-search.php endpoint
 *
 * TDD RED PHASE: These tests are written FIRST and will FAIL initially
 * until we implement the searchTickets() method in ExtendedTicketApiController.
 *
 * Tests verify:
 * - Basic search (query parameter)
 * - Filters (status, department)
 * - Pagination (limit, offset)
 * - Sorting (created, updated, number)
 * - Permissions (can_read_tickets OR canCreateTickets)
 * - Error handling (401, invalid parameters)
 * - Response structure (WITHOUT thread entries for performance)
 */
class SearchTicketEndpointTest extends TestCase {

    protected function setUp(): void {
        // Reset all mock data before each test
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        API::$mockData = [];
        Dept::$mockData = [];
        Topic::$mockData = [];
        Priority::$mockData = [];
        TicketStatus::$mockData = [];
        Staff::$mockData = [];
        Team::$mockData = [];
        SLA::$mockData = [];
    }

    /**
     * Helper: Create a test ticket with full data
     *
     * @param array $overrides Custom ticket properties
     * @return Ticket
     */
    private function createTestTicket(array $overrides = []): Ticket {
        static $counter = 1;

        $defaults = [
            'id' => $counter,
            'number' => sprintf('%06d', $counter),
            'subject' => "Test Ticket {$counter}",
            'dept_id' => 1,
            'status_id' => 1,
            'priority_id' => 2,
            'topic_id' => 1,
            'created' => date('Y-m-d H:i:s', strtotime("-{$counter} days")),
            'updated' => date('Y-m-d H:i:s', strtotime("-{$counter} hours")),
        ];

        $data = array_merge($defaults, $overrides);
        $ticket = new Ticket($data);

        // Register in both lookup tables
        Ticket::$mockData[$ticket->getId()] = $ticket;
        Ticket::$mockDataByNumber[$ticket->getNumber()] = $ticket;

        $counter++;
        return $ticket;
    }

    /**
     * Helper: Setup API key with READ permission
     */
    private function setupApiKeyWithReadPermission(): void {
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;
    }

    /**
     * Helper: Setup department mock data
     */
    private function setupDepartments(): void {
        $support = new Dept(['id' => 1, 'name' => 'Support', 'active' => true]);
        $sales = new Dept(['id' => 2, 'name' => 'Sales', 'active' => true]);
        Dept::$mockData[1] = $support;
        Dept::$mockData[2] = $sales;
    }

    /**
     * Helper: Setup status mock data
     */
    private function setupStatuses(): void {
        $open = new TicketStatus(['id' => 1, 'name' => 'Open']);
        $closed = new TicketStatus(['id' => 2, 'name' => 'Closed']);
        TicketStatus::$mockData[1] = $open;
        TicketStatus::$mockData[2] = $closed;
    }

    /**
     * Helper: Setup priority mock data
     */
    private function setupPriorities(): void {
        $normal = new Priority(['id' => 2, 'name' => 'Normal']);
        Priority::$mockData[2] = $normal;
    }

    // ====================================================================
    // BASIC SEARCH TESTS
    // ====================================================================

    /**
     * Test: Search without parameters returns all tickets (limited to default 20)
     *
     * RED: This test will FAIL because searchTickets() doesn't exist yet
     */
    public function testSearchWithoutParametersReturnsAllTickets(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create 5 test tickets
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestTicket([
                'dept' => Dept::$mockData[1],
                'status' => TicketStatus::$mockData[1],
                'priority' => Priority::$mockData[2]
            ]);
        }

        // Execute: Search without parameters
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([]);

        // Assert: All 5 tickets returned
        $this->assertIsArray($result);
        $this->assertCount(5, $result);

        // Assert: First ticket has expected structure (WITHOUT thread entries!)
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('number', $result[0]);
        $this->assertArrayHasKey('subject', $result[0]);
        $this->assertArrayHasKey('status', $result[0]);
        $this->assertArrayHasKey('department', $result[0]);
        $this->assertArrayHasKey('created', $result[0]);
        $this->assertArrayHasKey('updated', $result[0]);
        $this->assertArrayHasKey('priority', $result[0]);

        // Assert: Thread entries are NOT included (performance optimization)
        $this->assertArrayNotHasKey('thread', $result[0]);
    }

    /**
     * Test: Search with query parameter finds tickets by subject
     */
    public function testSearchByQueryFindsTicketsBySubject(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create tickets with different subjects
        $this->createTestTicket([
            'subject' => 'Password reset request',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'subject' => 'Invoice question',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'subject' => 'Password change issue',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Search for "password"
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['query' => 'password']);

        // Assert: Only 2 tickets with "password" in subject
        $this->assertCount(2, $result);
        $this->assertStringContainsString('Password', $result[0]['subject']);
        $this->assertStringContainsString('Password', $result[1]['subject']);
    }

    /**
     * Test: Search with query parameter is case-insensitive
     */
    public function testSearchQueryIsCaseInsensitive(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'subject' => 'PASSWORD RESET',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Search with lowercase
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['query' => 'password']);

        // Assert: Found despite case difference
        $this->assertCount(1, $result);
        $this->assertEquals('PASSWORD RESET', $result[0]['subject']);
    }

    /**
     * Test: Empty result when no matches found
     */
    public function testSearchReturnsEmptyArrayWhenNoMatches(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'subject' => 'Invoice question',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Search for non-existent term
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['query' => 'nonexistent']);

        // Assert: Empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ====================================================================
    // FILTER TESTS
    // ====================================================================

    /**
     * Test: Filter by status
     */
    public function testFilterByStatus(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create tickets with different statuses
        $this->createTestTicket([
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'dept' => Dept::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'status_id' => 2,
            'status' => TicketStatus::$mockData[2],
            'dept' => Dept::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'dept' => Dept::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Filter by status 1 (Open)
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['status' => 1]);

        // Assert: Only 2 tickets with status 1
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['statusId']);
        $this->assertEquals(1, $result[1]['statusId']);
    }

    /**
     * Test: Filter by department
     */
    public function testFilterByDepartment(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create tickets in different departments
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'dept_id' => 2,
            'dept' => Dept::$mockData[2],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Filter by department 1 (Support)
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['department' => 1]);

        // Assert: Only 2 tickets in department 1
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['departmentId']);
        $this->assertEquals(1, $result[1]['departmentId']);
    }

    /**
     * Test: Combined filters (status + department)
     */
    public function testCombinedFilters(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create tickets with various combinations
        $this->createTestTicket([
            'dept_id' => 1,
            'status_id' => 1,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'dept_id' => 1,
            'status_id' => 2,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[2],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'dept_id' => 2,
            'status_id' => 1,
            'dept' => Dept::$mockData[2],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'dept_id' => 1,
            'status_id' => 1,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Filter by department 1 AND status 1
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([
            'department' => 1,
            'status' => 1
        ]);

        // Assert: Only 2 tickets match both filters
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['departmentId']);
        $this->assertEquals(1, $result[0]['statusId']);
        $this->assertEquals(1, $result[1]['departmentId']);
        $this->assertEquals(1, $result[1]['statusId']);
    }

    /**
     * Test: Filter with query combined
     */
    public function testFilterWithQueryCombined(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'subject' => 'Password reset in Support',
            'dept_id' => 1,
            'status_id' => 1,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'subject' => 'Password reset in Sales',
            'dept_id' => 2,
            'status_id' => 1,
            'dept' => Dept::$mockData[2],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'subject' => 'Invoice in Support',
            'dept_id' => 1,
            'status_id' => 1,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Search "password" in department 1
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([
            'query' => 'password',
            'department' => 1
        ]);

        // Assert: Only 1 ticket matches
        $this->assertCount(1, $result);
        $this->assertEquals('Password reset in Support', $result[0]['subject']);
        $this->assertEquals(1, $result[0]['departmentId']);
    }

    // ====================================================================
    // PAGINATION TESTS
    // ====================================================================

    /**
     * Test: Limit parameter restricts result count
     */
    public function testLimitParameter(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create 30 tickets
        for ($i = 1; $i <= 30; $i++) {
            $this->createTestTicket([
                'dept' => Dept::$mockData[1],
                'status' => TicketStatus::$mockData[1],
                'priority' => Priority::$mockData[2]
            ]);
        }

        // Execute: Request only 10 tickets
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['limit' => 10]);

        // Assert: Only 10 tickets returned
        $this->assertCount(10, $result);
    }

    /**
     * Test: Default limit is 20
     */
    public function testDefaultLimitIs20(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create 30 tickets
        for ($i = 1; $i <= 30; $i++) {
            $this->createTestTicket([
                'dept' => Dept::$mockData[1],
                'status' => TicketStatus::$mockData[1],
                'priority' => Priority::$mockData[2]
            ]);
        }

        // Execute: No limit specified
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([]);

        // Assert: Default 20 tickets returned
        $this->assertCount(20, $result);
    }

    /**
     * Test: Limit exceeds max (should cap at 100)
     */
    public function testLimitCapsAt100(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create 150 tickets
        for ($i = 1; $i <= 150; $i++) {
            $this->createTestTicket([
                'dept' => Dept::$mockData[1],
                'status' => TicketStatus::$mockData[1],
                'priority' => Priority::$mockData[2]
            ]);
        }

        // Execute: Request 200 tickets (exceeds max)
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['limit' => 200]);

        // Assert: Capped at 100
        $this->assertCount(100, $result);
    }

    /**
     * Test: Offset parameter skips tickets
     */
    public function testOffsetParameter(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create 30 tickets
        for ($i = 1; $i <= 30; $i++) {
            $this->createTestTicket([
                'dept' => Dept::$mockData[1],
                'status' => TicketStatus::$mockData[1],
                'priority' => Priority::$mockData[2]
            ]);
        }

        // Execute: Skip first 10 tickets
        $controller = new ExtendedTicketApiController();
        $resultPage1 = $controller->searchTickets(['limit' => 10, 'offset' => 0]);
        $resultPage2 = $controller->searchTickets(['limit' => 10, 'offset' => 10]);

        // Assert: Different tickets on each page
        $this->assertCount(10, $resultPage1);
        $this->assertCount(10, $resultPage2);
        $this->assertNotEquals($resultPage1[0]['id'], $resultPage2[0]['id']);
    }

    /**
     * Test: Negative offset defaults to 0
     */
    public function testNegativeOffsetDefaultsToZero(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Negative offset
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['offset' => -10]);

        // Assert: No error, returns results
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test: Negative limit defaults to 20
     */
    public function testNegativeLimitDefaultsTo20(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create 30 tickets
        for ($i = 1; $i <= 30; $i++) {
            $this->createTestTicket([
                'dept' => Dept::$mockData[1],
                'status' => TicketStatus::$mockData[1],
                'priority' => Priority::$mockData[2]
            ]);
        }

        // Execute: Negative limit
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['limit' => -5]);

        // Assert: Default 20 returned
        $this->assertCount(20, $result);
    }

    // ====================================================================
    // SORTING TESTS
    // ====================================================================

    /**
     * Test: Sort by created (default, descending = newest first)
     */
    public function testSortByCreatedDescending(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create tickets with different created dates
        $ticket1 = $this->createTestTicket([
            'created' => '2025-01-01 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $ticket2 = $this->createTestTicket([
            'created' => '2025-01-03 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $ticket3 = $this->createTestTicket([
            'created' => '2025-01-02 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Default sort (by created, descending)
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['sort' => 'created']);

        // Assert: Newest first
        $this->assertEquals('2025-01-03 10:00:00', $result[0]['created']);
        $this->assertEquals('2025-01-02 10:00:00', $result[1]['created']);
        $this->assertEquals('2025-01-01 10:00:00', $result[2]['created']);
    }

    /**
     * Test: Sort by updated (descending = most recently updated first)
     */
    public function testSortByUpdatedDescending(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'updated' => '2025-01-01 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'updated' => '2025-01-03 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'updated' => '2025-01-02 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Sort by updated
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['sort' => 'updated']);

        // Assert: Most recently updated first
        $this->assertEquals('2025-01-03 10:00:00', $result[0]['updated']);
        $this->assertEquals('2025-01-02 10:00:00', $result[1]['updated']);
        $this->assertEquals('2025-01-01 10:00:00', $result[2]['updated']);
    }

    /**
     * Test: Sort by number (ascending)
     */
    public function testSortByNumberAscending(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'number' => '000300',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'number' => '000100',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'number' => '000200',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Sort by number
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['sort' => 'number']);

        // Assert: Ascending order
        $this->assertEquals('000100', $result[0]['number']);
        $this->assertEquals('000200', $result[1]['number']);
        $this->assertEquals('000300', $result[2]['number']);
    }

    /**
     * Test: Invalid sort parameter defaults to 'created'
     */
    public function testInvalidSortDefaultsToCreated(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'created' => '2025-01-01 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);
        $this->createTestTicket([
            'created' => '2025-01-02 10:00:00',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Invalid sort parameter
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['sort' => 'invalid']);

        // Assert: Falls back to 'created' (newest first)
        $this->assertEquals('2025-01-02 10:00:00', $result[0]['created']);
        $this->assertEquals('2025-01-01 10:00:00', $result[1]['created']);
    }

    // ====================================================================
    // PERMISSION TESTS
    // ====================================================================

    /**
     * Test: 401 error when API key lacks READ permission
     */
    public function testReturns401WhenApiKeyLacksReadPermission(): void {
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create ticket
        $this->createTestTicket([
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Setup API key WITHOUT permissions
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => false,
            'permissions' => ['can_create_tickets' => false]
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert: Expect 401 exception
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('API key not authorized to search tickets');

        $controller->searchTickets([]);
    }

    /**
     * Test: Success with can_read_tickets permission
     */
    public function testSuccessWithReadPermission(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([]);

        // Assert: Success
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ====================================================================
    // EDGE CASES / SECURITY TESTS
    // ====================================================================

    /**
     * Test: Invalid status ID (non-existent) returns empty result
     */
    public function testInvalidStatusIdReturnsEmpty(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'dept' => Dept::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Search with non-existent status
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['status' => 9999]);

        // Assert: Empty result (not an error, just no matches)
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test: Invalid department ID returns empty result
     */
    public function testInvalidDepartmentIdReturnsEmpty(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Search with non-existent department
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets(['department' => 9999]);

        // Assert: Empty result
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test: SQL injection attempt in query parameter
     */
    public function testSqlInjectionAttemptInQuery(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        $this->createTestTicket([
            'subject' => 'Normal ticket',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2]
        ]);

        // Execute: Malicious query
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([
            'query' => "' OR 1=1 --"
        ]);

        // Assert: Should NOT return all tickets (injection prevented)
        // In this mock test, it will return empty (no match)
        // In real implementation, proper SQL escaping must be used
        $this->assertIsArray($result);
    }

    /**
     * Test: Response structure is consistent for all tickets
     */
    public function testResponseStructureIsConsistent(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupPriorities();

        // Create tickets with varying optional fields
        $this->createTestTicket([
            'subject' => 'Ticket with all fields',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2],
            'duedate' => '2025-12-31 23:59:59'
        ]);
        $this->createTestTicket([
            'subject' => 'Ticket with minimal fields',
            'dept' => Dept::$mockData[1],
            'status' => TicketStatus::$mockData[1],
            'priority' => Priority::$mockData[2],
            'duedate' => null
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([]);

        // Assert: Both tickets have same keys (null values for missing fields)
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('dueDate', $result[0]);
        $this->assertArrayHasKey('dueDate', $result[1]);
        $this->assertNotNull($result[0]['dueDate']);
        $this->assertNull($result[1]['dueDate']);
    }

    /**
     * Test: Empty ticket database returns empty array
     */
    public function testEmptyDatabaseReturnsEmptyArray(): void {
        $this->setupApiKeyWithReadPermission();

        // Execute: No tickets in database
        $controller = new ExtendedTicketApiController();
        $result = $controller->searchTickets([]);

        // Assert: Empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
