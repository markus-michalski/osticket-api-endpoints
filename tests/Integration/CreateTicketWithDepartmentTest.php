<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration Test: CREATE Ticket with departmentId
 *
 * Tests that departmentId is properly set even if Topic would override it.
 * This test was written AFTER the implementation (bad TDD practice!).
 *
 * Context: setDeptId() alone doesn't work reliably, so createTicket()
 * automatically calls update() after creation to force the department.
 */
class CreateTicketWithDepartmentTest extends TestCase {

    private $controller;
    private $mockApiKey;

    protected function setUp(): void {
        // Reset all mock data
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        Dept::$mockData = [];
        Topic::$mockData = [];
        API::$mockData = [];

        // Setup mock departments
        Dept::$mockData[1] = new Dept(['id' => 1, 'name' => 'Support', 'active' => true]);
        Dept::$mockData[2] = new Dept(['id' => 2, 'name' => 'Sales', 'active' => true]);
        Dept::$mockData[3] = new Dept(['id' => 3, 'name' => 'Development', 'active' => true]);

        // Setup mock topic that routes to department 1 by default
        Topic::$mockData[10] = new Topic(['id' => 10, 'name' => 'General Inquiry', 'active' => true]);

        // Setup mock API key with CREATE permission
        $this->mockApiKey = new API([
            'key' => 'test-api-key-123',
            'permissions' => ['can_create_tickets' => true],
        ]);
        API::$mockData['test-api-key-123'] = $this->mockApiKey;

        // Create controller
        require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';
        $this->controller = new ExtendedTicketApiController($this->mockApiKey);
    }

    /**
     * Test that departmentId overrides Topic's default department
     *
     * Scenario:
     * - Topic 10 routes to Department 1 (Support) by default
     * - User explicitly provides departmentId=3 (Development)
     * - Expected: Ticket should be in Department 3, not Department 1
     */
    public function testCreateTicketWithDepartmentIdOverridesTopic(): void {
        $data = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'subject' => 'Test Ticket',
            'message' => 'This should go to Development, not Support',
            'topicId' => 10,  // Routes to Department 1 by default
            'deptId' => 3,    // But we explicitly want Department 3
        ];

        $ticket = $this->controller->createTicket($data, 'API');

        $this->assertNotNull($ticket, 'Ticket should be created');
        $this->assertEquals(3, $ticket->getDeptId(),
            'Department should be 3 (Development), not 1 (Support from Topic)');
    }

    /**
     * Test that departmentId works without Topic
     */
    public function testCreateTicketWithDepartmentIdOnly(): void {
        $data = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'subject' => 'Test Ticket',
            'message' => 'This should go to Sales',
            'deptId' => 2,  // Department 2 (Sales)
        ];

        $ticket = $this->controller->createTicket($data, 'API');

        $this->assertNotNull($ticket, 'Ticket should be created');
        $this->assertEquals(2, $ticket->getDeptId(), 'Department should be 2 (Sales)');
    }

    /**
     * Test that CREATE with parentTicketId also works
     */
    public function testCreateTicketWithParentAndDepartment(): void {
        // Create parent ticket first
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => '100001',
            'subject' => 'Parent Ticket',
            'dept_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;
        Ticket::$mockDataByNumber['100001'] = $parentTicket;

        $data = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'subject' => 'Child Ticket',
            'message' => 'This is a follow-up',
            'parentTicketNumber' => '100001',  // Use ticket NUMBER, not ID
            'deptId' => 3,  // Different department than parent
        ];

        $ticket = $this->controller->createTicket($data, 'API');

        $this->assertNotNull($ticket, 'Ticket should be created');
        $this->assertEquals(3, $ticket->getDeptId(), 'Department should be 3');
        $this->assertEquals(100, $ticket->getPid(), 'Parent should be set to 100');
    }
}
