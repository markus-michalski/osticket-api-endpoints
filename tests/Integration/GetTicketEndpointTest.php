<?php

use PHPUnit\Framework\TestCase;

// Load the controller
require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';

/**
 * Integration Tests for GET /api/tickets-get.php endpoint
 *
 * Tests the complete workflow of retrieving tickets via API including:
 * - Ticket lookup by number (primary method)
 * - Ticket lookup by ID (fallback)
 * - Thread entries retrieval
 * - Permission checking (can_read_tickets OR canCreateTickets)
 * - Error handling (404, 401)
 * - Response structure validation
 *
 * These tests verify that bugs like wrong lookup method and
 * non-existent getFormat() calls are prevented.
 */
class GetTicketEndpointTest extends TestCase {

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
     * Test successful ticket retrieval by NUMBER (primary lookup method)
     *
     * This test verifies:
     * - Ticket is found via lookupByNumber() (not lookup())
     * - Full ticket data is returned
     * - All properties are correctly mapped
     */
    public function testGetTicketByNumberReturnsFullTicketData(): void {
        // Setup: Create test data
        $dept = new Dept(['id' => 5, 'name' => 'Support', 'active' => true]);
        Dept::$mockData[5] = $dept;

        $priority = new Priority(['id' => 2, 'name' => 'Normal']);
        Priority::$mockData[2] = $priority;

        $status = new TicketStatus(['id' => 1, 'name' => 'Open']);
        TicketStatus::$mockData[1] = $status;

        $topic = new Topic(['id' => 3, 'name' => 'General Inquiry', 'active' => true]);
        Topic::$mockData[3] = $topic;

        // Create ticket with thread entries
        $thread = new Thread([
            new ThreadEntry([
                'id' => 1,
                'type' => 'M',
                'poster' => 'Test User',
                'body' => 'This is the initial message',
                'user_id' => 10
            ]),
            new ThreadEntry([
                'id' => 2,
                'type' => 'R',
                'poster' => 'Support Agent',
                'body' => 'Thank you for contacting support',
                'staff_id' => 5,
                'staff' => new Staff(['id' => 5, 'name' => 'Support Agent', 'active' => true])
            ])
        ]);

        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket Subject',
            'dept_id' => 5,
            'status_id' => 1,
            'priority_id' => 2,
            'topic_id' => 3,
            'user_id' => 10,
            'user_name' => 'Test User',
            'user_email' => 'test@example.com',
            'source' => 'API',
            'ip' => '192.168.1.100',
            'thread' => $thread,
            'dept' => $dept,
            'status' => $status,
            'priority' => $priority,
            'topic' => $topic
        ]);

        // Register ticket in BOTH lookup tables (important!)
        Ticket::$mockDataByNumber['781258'] = $ticket;
        Ticket::$mockData[123] = $ticket;

        // Setup API key with READ permission
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Call controller method
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('781258');

        // Assert: Verify response structure and data
        $this->assertIsArray($result);
        $this->assertEquals(123, $result['id']);
        $this->assertEquals('781258', $result['number']);
        $this->assertEquals('Test Ticket Subject', $result['subject']);
        $this->assertEquals(5, $result['departmentId']);
        $this->assertEquals('Support', $result['department']);
        $this->assertEquals(1, $result['statusId']);
        $this->assertEquals('Open', $result['status']);
        $this->assertEquals(2, $result['priorityId']);
        $this->assertEquals('Normal', $result['priority']);
        $this->assertEquals(3, $result['topicId']);
        $this->assertEquals('General Inquiry', $result['topic']);
        $this->assertEquals(10, $result['userId']);
        $this->assertEquals('Test User', $result['user']['name']);
        $this->assertEquals('test@example.com', $result['user']['email']);
        $this->assertEquals('API', $result['source']);
        $this->assertEquals('192.168.1.100', $result['ip']);

        // Assert: Verify thread entries are included
        $this->assertArrayHasKey('thread', $result);
        $this->assertCount(2, $result['thread']);

        // Assert: Verify first thread entry (user message)
        $this->assertEquals(1, $result['thread'][0]['id']);
        $this->assertEquals('M', $result['thread'][0]['type']);
        $this->assertEquals('Test User', $result['thread'][0]['poster']);
        $this->assertEquals('This is the initial message', $result['thread'][0]['body']);
        $this->assertEquals(10, $result['thread'][0]['userId']);

        // Assert: Verify second thread entry (staff response)
        $this->assertEquals(2, $result['thread'][1]['id']);
        $this->assertEquals('R', $result['thread'][1]['type']);
        $this->assertEquals('Support Agent', $result['thread'][1]['poster']);
        $this->assertEquals('Thank you for contacting support', $result['thread'][1]['body']);
        $this->assertEquals(5, $result['thread'][1]['staffId']);
        $this->assertEquals('Support Agent', $result['thread'][1]['staff']);
    }

    /**
     * Test ticket retrieval by ID (fallback when lookupByNumber fails)
     *
     * This verifies that if lookupByNumber() returns null,
     * the controller falls back to lookup() by ID.
     */
    public function testGetTicketByIdFallsBackWhenNumberLookupFails(): void {
        // Setup: Create ticket registered ONLY by ID (not by number)
        $dept = new Dept(['id' => 5, 'name' => 'Support', 'active' => true]);
        Dept::$mockData[5] = $dept;

        $ticket = new Ticket([
            'id' => 456,
            'number' => '999888',
            'subject' => 'Ticket found by ID',
            'dept_id' => 5,
            'dept' => $dept
        ]);

        // Only register by ID (not by number)
        Ticket::$mockData[456] = $ticket;

        // Setup API key with READ permission
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Call controller with ID (fallback)
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket(456);

        // Assert: Ticket was found via ID lookup
        $this->assertEquals(456, $result['id']);
        $this->assertEquals('999888', $result['number']);
        $this->assertEquals('Ticket found by ID', $result['subject']);
    }

    /**
     * Test 404 error when ticket is not found
     */
    public function testReturns404WhenTicketNotFound(): void {
        // Setup API key with READ permission
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert: Expect 404 exception
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Ticket not found');

        $controller->getTicket('999999');
    }

    /**
     * Test 401 error when API key lacks READ permission
     *
     * Verifies that without can_read_tickets AND without canCreateTickets,
     * the request is rejected with 401.
     */
    public function testReturns401WhenApiKeyLacksReadPermission(): void {
        // Setup: Create ticket
        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket'
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

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
        $this->expectExceptionMessage('API key not authorized to read tickets');

        $controller->getTicket('781258');
    }

    /**
     * Test that thread entries do NOT call non-existent getFormat() method
     *
     * This is a regression test for the bug where we tried to call
     * $entry->getFormat() which doesn't exist on ThreadEntry objects.
     */
    public function testThreadEntriesDoNotCallGetFormatMethod(): void {
        // Setup: Create ticket with thread
        $thread = new Thread([
            new ThreadEntry([
                'id' => 1,
                'type' => 'M',
                'body' => 'Test message'
            ])
        ]);

        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test',
            'thread' => $thread
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: This should NOT throw "Call to undefined method"
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('781258');

        // Assert: Thread entries are present and don't have 'format' field
        $this->assertCount(1, $result['thread']);
        $this->assertEquals('Test message', $result['thread'][0]['body']);
        $this->assertArrayNotHasKey('format', $result['thread'][0]);
    }

    /**
     * Test ticket with all optional fields (staff, team, SLA, etc.)
     */
    public function testGetTicketWithAllOptionalFields(): void {
        // Setup: Create all related objects
        $dept = new Dept(['id' => 5, 'name' => 'Support', 'active' => true]);
        Dept::$mockData[5] = $dept;

        $staff = new Staff(['id' => 10, 'name' => 'John Doe', 'active' => true]);
        Staff::$mockData[10] = $staff;

        $team = new Team(['id' => 3, 'name' => 'Level 2 Support']);
        Team::$mockData[3] = $team;

        $sla = new SLA(['id' => 2, 'name' => '24h Response', 'active' => true]);
        SLA::$mockData[2] = $sla;

        $priority = new Priority(['id' => 1, 'name' => 'High']);
        Priority::$mockData[1] = $priority;

        $status = new TicketStatus(['id' => 2, 'name' => 'In Progress']);
        TicketStatus::$mockData[2] = $status;

        $topic = new Topic(['id' => 5, 'name' => 'Technical Support', 'active' => true]);
        Topic::$mockData[5] = $topic;

        // Create ticket with ALL fields
        $ticket = new Ticket([
            'id' => 789,
            'number' => '555444',
            'subject' => 'Complex Ticket',
            'dept_id' => 5,
            'status_id' => 2,
            'priority_id' => 1,
            'topic_id' => 5,
            'user_id' => 20,
            'staff_id' => 10,
            'team_id' => 3,
            'sla_id' => 2,
            'is_overdue' => true,
            'is_answered' => true,
            'duedate' => '2024-12-31 23:59:59',
            'closed' => '2024-10-20 10:00:00',
            'dept' => $dept,
            'status' => $status,
            'priority' => $priority,
            'topic' => $topic,
            'staff' => $staff,
            'team' => $team,
            'sla' => $sla
        ]);
        Ticket::$mockDataByNumber['555444'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('555444');

        // Assert: All fields are present
        $this->assertEquals(789, $result['id']);
        $this->assertEquals('555444', $result['number']);
        $this->assertEquals('Complex Ticket', $result['subject']);
        $this->assertEquals(10, $result['staffId']);
        $this->assertEquals('John Doe', $result['staff']);
        $this->assertEquals(3, $result['teamId']);
        $this->assertEquals('Level 2 Support', $result['team']);
        $this->assertEquals(2, $result['slaId']);
        $this->assertEquals('24h Response', $result['sla']);
        $this->assertTrue($result['isOverdue']);
        $this->assertTrue($result['isAnswered']);
        $this->assertEquals('2024-12-31 23:59:59', $result['duedate']);
        $this->assertEquals('2024-10-20 10:00:00', $result['closed']);
    }

    /**
     * Test empty thread (no messages)
     */
    public function testGetTicketWithEmptyThread(): void {
        // Setup: Ticket without thread
        $ticket = new Ticket([
            'id' => 111,
            'number' => '111111',
            'subject' => 'No Thread',
            'thread' => null
        ]);
        Ticket::$mockDataByNumber['111111'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('111111');

        // Assert: Thread array is empty
        $this->assertArrayHasKey('thread', $result);
        $this->assertIsArray($result['thread']);
        $this->assertEmpty($result['thread']);
    }

    /**
     * Test ticket with internal note (type = 'N')
     */
    public function testGetTicketWithInternalNote(): void {
        // Setup: Create staff
        $staff = new Staff(['id' => 7, 'name' => 'Internal Agent', 'active' => true]);
        Staff::$mockData[7] = $staff;

        // Create thread with internal note
        $thread = new Thread([
            new ThreadEntry([
                'id' => 100,
                'type' => 'N', // Internal Note
                'poster' => 'Internal Agent',
                'body' => 'This is an internal note',
                'staff_id' => 7,
                'staff' => $staff
            ])
        ]);

        $ticket = new Ticket([
            'id' => 222,
            'number' => '222222',
            'subject' => 'Ticket with Note',
            'thread' => $thread
        ]);
        Ticket::$mockDataByNumber['222222'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('222222');

        // Assert: Internal note is present
        $this->assertCount(1, $result['thread']);
        $this->assertEquals('N', $result['thread'][0]['type']);
        $this->assertEquals('This is an internal note', $result['thread'][0]['body']);
        $this->assertEquals(7, $result['thread'][0]['staffId']);
        $this->assertEquals('Internal Agent', $result['thread'][0]['staff']);
    }

    /**
     * Test lookup priority: Number > ID
     *
     * This test ensures that lookupByNumber() is called FIRST,
     * and lookup() is only used as a fallback.
     */
    public function testLookupPriorityNumberBeforeId(): void {
        // Setup: Create TWO different tickets with same ID but different number
        // This simulates the scenario where "123" could be either a number OR an ID

        $ticketByNumber = new Ticket([
            'id' => 999,
            'number' => '123', // Number is "123"
            'subject' => 'Found by Number'
        ]);
        Ticket::$mockDataByNumber['123'] = $ticketByNumber;

        $ticketById = new Ticket([
            'id' => 123, // ID is 123
            'number' => '456',
            'subject' => 'Found by ID'
        ]);
        Ticket::$mockData[123] = $ticketById;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Search for "123"
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('123');

        // Assert: Should find ticket by NUMBER (not by ID)
        $this->assertEquals(999, $result['id']); // ID is 999
        $this->assertEquals('123', $result['number']); // Number is "123"
        $this->assertEquals('Found by Number', $result['subject']);
    }
}
