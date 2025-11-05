<?php

use PHPUnit\Framework\TestCase;

// Load the controller
require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';

/**
 * Integration Tests for GET /api/tickets-stats.php endpoint
 *
 * TDD RED PHASE: These tests are written FIRST and will FAIL initially
 * until we implement the getTicketStats() method in ExtendedTicketApiController.
 *
 * Tests verify:
 * - Global statistics (total, open, closed, overdue)
 * - Department-based statistics grouping
 * - Staff-based statistics with department breakdown
 * - Overdue ticket detection based on SLA/duedate
 * - Permission checking (can_read_tickets OR canCreateTickets)
 * - Response structure and data accuracy
 * - Edge cases (empty database, no staff assignments, etc.)
 */
class TicketStatsEndpointTest extends TestCase {

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

    // ====================================================================
    // HELPER METHODS
    // ====================================================================

    /**
     * Setup API key with READ permission
     */
    private function setupApiKeyWithReadPermission(): void {
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;
    }

    /**
     * Setup departments
     */
    private function setupDepartments(): void {
        $support = new Dept(['id' => 1, 'name' => 'Support', 'active' => true]);
        $sales = new Dept(['id' => 2, 'name' => 'Sales', 'active' => true]);
        $billing = new Dept(['id' => 3, 'name' => 'Billing', 'active' => true]);
        Dept::$mockData[1] = $support;
        Dept::$mockData[2] = $sales;
        Dept::$mockData[3] = $billing;
    }

    /**
     * Setup statuses (1=Open, 2=Closed)
     */
    private function setupStatuses(): void {
        $open = new TicketStatus(['id' => 1, 'name' => 'Open']);
        $closed = new TicketStatus(['id' => 2, 'name' => 'Closed']);
        TicketStatus::$mockData[1] = $open;
        TicketStatus::$mockData[2] = $closed;
    }

    /**
     * Setup staff members
     */
    private function setupStaff(): void {
        $john = new Staff(['id' => 1, 'name' => 'John Doe', 'active' => true]);
        $jane = new Staff(['id' => 2, 'name' => 'Jane Smith', 'active' => true]);
        $alice = new Staff(['id' => 3, 'name' => 'Alice Johnson', 'active' => true]);
        Staff::$mockData[1] = $john;
        Staff::$mockData[2] = $jane;
        Staff::$mockData[3] = $alice;
    }

    /**
     * Create test ticket
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
            'is_overdue' => false,
            'closed' => null,
            'staff_id' => null,
        ];

        $data = array_merge($defaults, $overrides);
        $ticket = new Ticket($data);

        // Register in both lookup tables
        Ticket::$mockData[$ticket->getId()] = $ticket;
        Ticket::$mockDataByNumber[$ticket->getNumber()] = $ticket;

        $counter++;
        return $ticket;
    }

    // ====================================================================
    // BASIC STRUCTURE TESTS
    // ====================================================================

    /**
     * Test: Empty database returns all zeros
     *
     * RED: This test will FAIL because getTicketStats() doesn't exist yet
     */
    public function testEmptyDatabaseReturnsAllZeros(): void {
        $this->setupApiKeyWithReadPermission();

        // Execute: Get stats from empty database
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: All global stats are zero
        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['open']);
        $this->assertEquals(0, $result['closed']);
        $this->assertEquals(0, $result['overdue']);

        // Assert: by_department is empty object
        $this->assertIsArray($result['by_department']);
        $this->assertEmpty($result['by_department']);

        // Assert: by_staff is empty array
        $this->assertIsArray($result['by_staff']);
        $this->assertEmpty($result['by_staff']);
    }

    /**
     * Test: Response structure with single ticket
     */
    public function testResponseStructureWithSingleTicket(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupStaff();

        // Create single open ticket in Support department
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 1,
            'staff' => Staff::$mockData[1],
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Global stats
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['open']);
        $this->assertEquals(0, $result['closed']);
        $this->assertEquals(0, $result['overdue']);

        // Assert: by_department structure
        $this->assertArrayHasKey('Support', $result['by_department']);
        $this->assertEquals(1, $result['by_department']['Support']['total']);
        $this->assertEquals(1, $result['by_department']['Support']['open']);
        $this->assertEquals(0, $result['by_department']['Support']['closed']);
        $this->assertEquals(0, $result['by_department']['Support']['overdue']);

        // Assert: by_staff structure
        $this->assertCount(1, $result['by_staff']);
        $this->assertEquals(1, $result['by_staff'][0]['staff_id']);
        $this->assertEquals('John Doe', $result['by_staff'][0]['staff_name']);
        $this->assertEquals(1, $result['by_staff'][0]['total']);

        // Assert: by_staff departments structure
        $this->assertArrayHasKey('departments', $result['by_staff'][0]);
        $this->assertArrayHasKey('Support', $result['by_staff'][0]['departments']);
        $this->assertEquals(1, $result['by_staff'][0]['departments']['Support']['open']);
        $this->assertEquals(0, $result['by_staff'][0]['departments']['Support']['closed']);
        $this->assertEquals(0, $result['by_staff'][0]['departments']['Support']['overdue']);
    }

    // ====================================================================
    // GLOBAL STATISTICS TESTS
    // ====================================================================

    /**
     * Test: Global stats count correctly across departments
     */
    public function testGlobalStatsCountCorrectly(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create 15 tickets: 10 open, 5 closed
        for ($i = 1; $i <= 10; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
            ]);
        }
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestTicket([
                'dept_id' => 2,
                'dept' => Dept::$mockData[2],
                'status_id' => 2,
                'status' => TicketStatus::$mockData[2],
                'closed' => date('Y-m-d H:i:s'),
            ]);
        }

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Total is sum of all tickets
        $this->assertEquals(15, $result['total']);
        $this->assertEquals(10, $result['open']);
        $this->assertEquals(5, $result['closed']);
    }

    /**
     * Test: Overdue tickets are counted correctly
     */
    public function testOverdueTicketsCountedCorrectly(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create 8 tickets: 3 overdue, 5 not overdue
        for ($i = 1; $i <= 3; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'is_overdue' => true,
            ]);
        }
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'is_overdue' => false,
            ]);
        }

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Overdue count is correct
        $this->assertEquals(8, $result['total']);
        $this->assertEquals(3, $result['overdue']);
    }

    /**
     * Test: Closed tickets are NOT counted as open
     */
    public function testClosedTicketsNotCountedAsOpen(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create closed ticket
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 2,
            'status' => TicketStatus::$mockData[2],
            'closed' => date('Y-m-d H:i:s'),
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Open count is zero
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(0, $result['open']);
        $this->assertEquals(1, $result['closed']);
    }

    // ====================================================================
    // DEPARTMENT STATISTICS TESTS
    // ====================================================================

    /**
     * Test: Department stats are grouped correctly
     */
    public function testDepartmentStatsGroupedCorrectly(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create tickets in different departments
        // Support: 5 open, 2 closed, 1 overdue
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'is_overdue' => $i === 1,
            ]);
        }
        for ($i = 1; $i <= 2; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 2,
                'status' => TicketStatus::$mockData[2],
                'closed' => date('Y-m-d H:i:s'),
            ]);
        }

        // Sales: 3 open, 1 closed
        for ($i = 1; $i <= 3; $i++) {
            $this->createTestTicket([
                'dept_id' => 2,
                'dept' => Dept::$mockData[2],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
            ]);
        }
        $this->createTestTicket([
            'dept_id' => 2,
            'dept' => Dept::$mockData[2],
            'status_id' => 2,
            'status' => TicketStatus::$mockData[2],
            'closed' => date('Y-m-d H:i:s'),
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Support department stats
        $this->assertArrayHasKey('Support', $result['by_department']);
        $this->assertEquals(7, $result['by_department']['Support']['total']);
        $this->assertEquals(5, $result['by_department']['Support']['open']);
        $this->assertEquals(2, $result['by_department']['Support']['closed']);
        $this->assertEquals(1, $result['by_department']['Support']['overdue']);

        // Assert: Sales department stats
        $this->assertArrayHasKey('Sales', $result['by_department']);
        $this->assertEquals(4, $result['by_department']['Sales']['total']);
        $this->assertEquals(3, $result['by_department']['Sales']['open']);
        $this->assertEquals(1, $result['by_department']['Sales']['closed']);
        $this->assertEquals(0, $result['by_department']['Sales']['overdue']);
    }

    /**
     * Test: Departments are sorted alphabetically
     */
    public function testDepartmentsSortedAlphabetically(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create tickets in reverse alphabetical order
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1], // Support
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);
        $this->createTestTicket([
            'dept_id' => 2,
            'dept' => Dept::$mockData[2], // Sales
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);
        $this->createTestTicket([
            'dept_id' => 3,
            'dept' => Dept::$mockData[3], // Billing
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Departments are in alphabetical order
        $deptKeys = array_keys($result['by_department']);
        $this->assertEquals(['Billing', 'Sales', 'Support'], $deptKeys);
    }

    /**
     * Test: Department with no tickets is NOT included
     */
    public function testDepartmentWithNoTicketsNotIncluded(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create ticket only in Support
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Only Support is in by_department
        $this->assertArrayHasKey('Support', $result['by_department']);
        $this->assertArrayNotHasKey('Sales', $result['by_department']);
        $this->assertArrayNotHasKey('Billing', $result['by_department']);
    }

    // ====================================================================
    // STAFF STATISTICS TESTS
    // ====================================================================

    /**
     * Test: Staff stats with tickets assigned to staff
     */
    public function testStaffStatsWithAssignedTickets(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupStaff();

        // John: 5 tickets in Support (3 open, 2 closed)
        for ($i = 1; $i <= 3; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'staff_id' => 1,
                'staff' => Staff::$mockData[1],
            ]);
        }
        for ($i = 1; $i <= 2; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 2,
                'status' => TicketStatus::$mockData[2],
                'closed' => date('Y-m-d H:i:s'),
                'staff_id' => 1,
                'staff' => Staff::$mockData[1],
            ]);
        }

        // Jane: 3 tickets in Sales (2 open, 1 closed)
        for ($i = 1; $i <= 2; $i++) {
            $this->createTestTicket([
                'dept_id' => 2,
                'dept' => Dept::$mockData[2],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'staff_id' => 2,
                'staff' => Staff::$mockData[2],
            ]);
        }
        $this->createTestTicket([
            'dept_id' => 2,
            'dept' => Dept::$mockData[2],
            'status_id' => 2,
            'status' => TicketStatus::$mockData[2],
            'closed' => date('Y-m-d H:i:s'),
            'staff_id' => 2,
            'staff' => Staff::$mockData[2],
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: 2 staff members with tickets
        $this->assertCount(2, $result['by_staff']);

        // Assert: Find John's stats (sorted by name, so Jane first)
        $jane = $result['by_staff'][0];
        $john = $result['by_staff'][1];

        $this->assertEquals(2, $jane['staff_id']);
        $this->assertEquals('Jane Smith', $jane['staff_name']);
        $this->assertEquals(3, $jane['total']);

        $this->assertEquals(1, $john['staff_id']);
        $this->assertEquals('John Doe', $john['staff_name']);
        $this->assertEquals(5, $john['total']);
    }

    /**
     * Test: Staff with tickets in multiple departments
     */
    public function testStaffWithTicketsInMultipleDepartments(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupStaff();

        // John: 3 in Support, 2 in Sales
        for ($i = 1; $i <= 3; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'staff_id' => 1,
                'staff' => Staff::$mockData[1],
            ]);
        }
        for ($i = 1; $i <= 2; $i++) {
            $this->createTestTicket([
                'dept_id' => 2,
                'dept' => Dept::$mockData[2],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'staff_id' => 1,
                'staff' => Staff::$mockData[1],
            ]);
        }

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: John has departments breakdown
        $john = $result['by_staff'][0];
        $this->assertEquals(5, $john['total']);
        $this->assertArrayHasKey('Support', $john['departments']);
        $this->assertArrayHasKey('Sales', $john['departments']);
        $this->assertEquals(3, $john['departments']['Support']['open']);
        $this->assertEquals(2, $john['departments']['Sales']['open']);
    }

    /**
     * Test: Staff sorted by name
     */
    public function testStaffSortedByName(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupStaff();

        // Create tickets for staff in reverse order
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 1,
            'staff' => Staff::$mockData[1], // John Doe
        ]);
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 3,
            'staff' => Staff::$mockData[3], // Alice Johnson
        ]);
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 2,
            'staff' => Staff::$mockData[2], // Jane Smith
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Staff sorted alphabetically by name
        $this->assertEquals('Alice Johnson', $result['by_staff'][0]['staff_name']);
        $this->assertEquals('Jane Smith', $result['by_staff'][1]['staff_name']);
        $this->assertEquals('John Doe', $result['by_staff'][2]['staff_name']);
    }

    /**
     * Test: Tickets without staff assignment are NOT in by_staff
     */
    public function testUnassignedTicketsNotInStaffStats(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupStaff();

        // Create unassigned tickets
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => null,
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Global stats show ticket
        $this->assertEquals(1, $result['total']);

        // Assert: by_staff is empty (no staff assigned)
        $this->assertEmpty($result['by_staff']);
    }

    /**
     * Test: Staff overdue tickets counted per department
     */
    public function testStaffOverdueTicketsCountedPerDepartment(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupStaff();

        // John: 2 overdue in Support, 1 overdue in Sales
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 1,
            'staff' => Staff::$mockData[1],
            'is_overdue' => true,
        ]);
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 1,
            'staff' => Staff::$mockData[1],
            'is_overdue' => true,
        ]);
        $this->createTestTicket([
            'dept_id' => 2,
            'dept' => Dept::$mockData[2],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 1,
            'staff' => Staff::$mockData[1],
            'is_overdue' => true,
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: John's department stats show overdue
        $john = $result['by_staff'][0];
        $this->assertEquals(2, $john['departments']['Support']['overdue']);
        $this->assertEquals(1, $john['departments']['Sales']['overdue']);
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

        // Create ticket
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
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
        $this->expectExceptionMessage('API key not authorized to read ticket statistics');

        $controller->getTicketStats();
    }

    /**
     * Test: Success with can_read_tickets permission
     */
    public function testSuccessWithReadPermission(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Success
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['total']);
    }

    // ====================================================================
    // COMPLEX INTEGRATION TESTS
    // ====================================================================

    /**
     * Test: Complete real-world scenario with mixed data
     */
    public function testCompleteRealWorldScenario(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();
        $this->setupStaff();

        // Support Department:
        // - 10 tickets total
        // - 6 open (2 overdue), 4 closed
        // - 5 assigned to John (3 open, 2 closed)
        // - 3 assigned to Jane (2 open, 1 closed)
        // - 2 unassigned (1 open, 1 closed)

        // John's tickets in Support
        for ($i = 1; $i <= 3; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'staff_id' => 1,
                'staff' => Staff::$mockData[1],
                'is_overdue' => $i <= 2,
            ]);
        }
        for ($i = 1; $i <= 2; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 2,
                'status' => TicketStatus::$mockData[2],
                'closed' => date('Y-m-d H:i:s'),
                'staff_id' => 1,
                'staff' => Staff::$mockData[1],
            ]);
        }

        // Jane's tickets in Support
        for ($i = 1; $i <= 2; $i++) {
            $this->createTestTicket([
                'dept_id' => 1,
                'dept' => Dept::$mockData[1],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'staff_id' => 2,
                'staff' => Staff::$mockData[2],
            ]);
        }
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 2,
            'status' => TicketStatus::$mockData[2],
            'closed' => date('Y-m-d H:i:s'),
            'staff_id' => 2,
            'staff' => Staff::$mockData[2],
        ]);

        // Unassigned tickets in Support
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 2,
            'status' => TicketStatus::$mockData[2],
            'closed' => date('Y-m-d H:i:s'),
        ]);

        // Sales Department:
        // - 5 tickets total
        // - 3 open, 2 closed
        // - All assigned to Alice
        for ($i = 1; $i <= 3; $i++) {
            $this->createTestTicket([
                'dept_id' => 2,
                'dept' => Dept::$mockData[2],
                'status_id' => 1,
                'status' => TicketStatus::$mockData[1],
                'staff_id' => 3,
                'staff' => Staff::$mockData[3],
            ]);
        }
        for ($i = 1; $i <= 2; $i++) {
            $this->createTestTicket([
                'dept_id' => 2,
                'dept' => Dept::$mockData[2],
                'status_id' => 2,
                'status' => TicketStatus::$mockData[2],
                'closed' => date('Y-m-d H:i:s'),
                'staff_id' => 3,
                'staff' => Staff::$mockData[3],
            ]);
        }

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Global stats
        $this->assertEquals(15, $result['total']);
        $this->assertEquals(9, $result['open']);
        $this->assertEquals(6, $result['closed']);
        $this->assertEquals(2, $result['overdue']);

        // Assert: Support department stats
        $this->assertEquals(10, $result['by_department']['Support']['total']);
        $this->assertEquals(6, $result['by_department']['Support']['open']);
        $this->assertEquals(4, $result['by_department']['Support']['closed']);
        $this->assertEquals(2, $result['by_department']['Support']['overdue']);

        // Assert: Sales department stats
        $this->assertEquals(5, $result['by_department']['Sales']['total']);
        $this->assertEquals(3, $result['by_department']['Sales']['open']);
        $this->assertEquals(2, $result['by_department']['Sales']['closed']);
        $this->assertEquals(0, $result['by_department']['Sales']['overdue']);

        // Assert: 3 staff members with tickets
        $this->assertCount(3, $result['by_staff']);

        // Assert: Alice (alphabetically first)
        $alice = $result['by_staff'][0];
        $this->assertEquals(3, $alice['staff_id']);
        $this->assertEquals('Alice Johnson', $alice['staff_name']);
        $this->assertEquals(5, $alice['total']);
        $this->assertEquals(3, $alice['departments']['Sales']['open']);
        $this->assertEquals(2, $alice['departments']['Sales']['closed']);

        // Assert: Jane (alphabetically second)
        $jane = $result['by_staff'][1];
        $this->assertEquals(2, $jane['staff_id']);
        $this->assertEquals('Jane Smith', $jane['staff_name']);
        $this->assertEquals(3, $jane['total']);
        $this->assertEquals(2, $jane['departments']['Support']['open']);
        $this->assertEquals(1, $jane['departments']['Support']['closed']);

        // Assert: John (alphabetically third)
        $john = $result['by_staff'][2];
        $this->assertEquals(1, $john['staff_id']);
        $this->assertEquals('John Doe', $john['staff_name']);
        $this->assertEquals(5, $john['total']);
        $this->assertEquals(3, $john['departments']['Support']['open']);
        $this->assertEquals(2, $john['departments']['Support']['closed']);
        $this->assertEquals(2, $john['departments']['Support']['overdue']);
    }

    /**
     * Test: Department stats exclude tickets without department object
     */
    public function testDepartmentStatsExcludeTicketsWithoutDeptObject(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create ticket WITHOUT dept object (edge case)
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => null, // Missing dept object
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Global stats show ticket
        $this->assertEquals(1, $result['total']);

        // Assert: by_department is empty (no dept object)
        $this->assertEmpty($result['by_department']);
    }

    /**
     * Test: Staff stats exclude tickets without staff object
     */
    public function testStaffStatsExcludeTicketsWithoutStaffObject(): void {
        $this->setupApiKeyWithReadPermission();
        $this->setupDepartments();
        $this->setupStatuses();

        // Create ticket WITH staff_id but WITHOUT staff object (edge case)
        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
            'staff_id' => 1,
            'staff' => null, // Missing staff object
        ]);

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Global stats show ticket
        $this->assertEquals(1, $result['total']);

        // Assert: by_staff is empty (no staff object)
        $this->assertEmpty($result['by_staff']);
    }

    // ====================================================================
    // STATS PERMISSION TESTS (can_read_stats)
    // ====================================================================

    /**
     * Test: Success with can_read_stats permission (NEW dedicated permission)
     *
     * RED: This test will FAIL because can_read_stats permission doesn't exist yet
     */
    public function testSuccessWithStatsPermission(): void {
        $this->setupDepartments();
        $this->setupStatuses();

        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);

        // Setup API key with can_read_stats permission (but NOT can_read_tickets)
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_stats' => true,
            'can_read_tickets' => false
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Should succeed with STATS permission
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Success
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['total']);
    }

    /**
     * Test: Fallback to can_read_tickets when can_read_stats not granted
     *
     * RED: This test will FAIL because fallback logic doesn't exist yet
     */
    public function testFallbackToReadTicketsPermission(): void {
        $this->setupDepartments();
        $this->setupStatuses();

        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);

        // Setup API key WITHOUT can_read_stats but WITH can_read_tickets
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_stats' => false,
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Should succeed via fallback to can_read_tickets
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Success
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['total']);
    }

    /**
     * Test: Prioritizes can_read_stats over can_read_tickets
     *
     * RED: This test will FAIL because priority logic doesn't exist yet
     */
    public function testPrioritizesStatsOverReadPermission(): void {
        $this->setupDepartments();
        $this->setupStatuses();

        $this->createTestTicket([
            'dept_id' => 1,
            'dept' => Dept::$mockData[1],
            'status_id' => 1,
            'status' => TicketStatus::$mockData[1],
        ]);

        // Setup API key with BOTH can_read_stats AND can_read_tickets
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_stats' => true,
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Should succeed (verifies both permissions work)
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicketStats();

        // Assert: Success
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['total']);
    }

}
