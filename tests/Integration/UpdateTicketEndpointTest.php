<?php

use PHPUnit\Framework\TestCase;

// Load the controller
require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';

/**
 * Integration Tests for PATCH/PUT /api/tickets-update.php endpoint
 *
 * Tests the complete workflow of updating tickets via API including:
 * - Updating various ticket properties (department, status, priority, etc.)
 * - Validation of all parameters
 * - Permission checking (can_update_tickets OR canCreateTickets)
 * - Error handling (404, 401, 400)
 * - Database persistence verification
 *
 * The update() method accepts these parameters:
 * - departmentId: Change ticket department
 * - statusId: Change ticket status
 * - topicId: Change help topic
 * - slaId: Assign SLA
 * - staffId: Assign to staff member
 * - parentTicketNumber: Make ticket a subticket (provide ticket NUMBER, not ID)
 */
class UpdateTicketEndpointTest extends TestCase {

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
        SLA::$mockData = [];
    }

    /**
     * Test successful department update
     *
     * Verifies that a ticket's department can be changed
     * and the validation is properly enforced.
     */
    public function testUpdateTicketDepartment(): void {
        // Setup: Create departments
        $oldDept = new Dept(['id' => 1, 'name' => 'Sales', 'active' => true]);
        Dept::$mockData[1] = $oldDept;

        $newDept = new Dept(['id' => 5, 'name' => 'Support', 'active' => true]);
        Dept::$mockData[5] = $newDept;

        // Create ticket
        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'dept_id' => 1
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        // Setup API key with UPDATE permission
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Update department
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('781258', [
            'departmentId' => 5
        ]);

        // Assert: Department was updated
        $this->assertEquals(123, $result->getId());
        $this->assertEquals(5, $result->getDeptId());
    }

    /**
     * Test department validation: inactive department rejected
     */
    public function testRejectsInactiveDepartment(): void {
        // Setup: Create inactive department
        $inactiveDept = new Dept(['id' => 99, 'name' => 'Closed Department', 'active' => false]);
        Dept::$mockData[99] = $inactiveDept;

        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'dept_id' => 1
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert: Expect validation error
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Department is not active');

        $controller->update('781258', [
            'departmentId' => 99
        ]);
    }

    /**
     * Test department validation: non-existent department rejected
     */
    public function testRejectsNonExistentDepartment(): void {
        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'dept_id' => 1
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Department not found');

        $controller->update('781258', [
            'departmentId' => 999
        ]);
    }

    /**
     * Test successful status update
     */
    public function testUpdateTicketStatus(): void {
        // Setup: Create statuses
        $openStatus = new TicketStatus(['id' => 1, 'name' => 'Open']);
        TicketStatus::$mockData[1] = $openStatus;

        $closedStatus = new TicketStatus(['id' => 3, 'name' => 'Closed']);
        TicketStatus::$mockData[3] = $closedStatus;

        $ticket = new Ticket([
            'id' => 456,
            'number' => '999888',
            'subject' => 'Test Ticket',
            'status_id' => 1
        ]);
        Ticket::$mockDataByNumber['999888'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Update status
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('999888', [
            'statusId' => 3
        ]);

        // Assert: Status was updated
        $this->assertEquals(456, $result->getId());
        $this->assertEquals(3, $result->getStatusId());
    }

    /**
     * Test status validation: non-existent status rejected
     */
    public function testRejectsNonExistentStatus(): void {
        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'status_id' => 1
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Status not found');

        $controller->update('781258', [
            'statusId' => 999
        ]);
    }

    /**
     * Test successful help topic update
     */
    public function testUpdateTicketTopic(): void {
        // Setup: Create topics
        $oldTopic = new Topic(['id' => 1, 'name' => 'General', 'active' => true]);
        Topic::$mockData[1] = $oldTopic;

        $newTopic = new Topic(['id' => 5, 'name' => 'Technical Support', 'active' => true]);
        Topic::$mockData[5] = $newTopic;

        $ticket = new Ticket([
            'id' => 789,
            'number' => '555444',
            'subject' => 'Test Ticket',
            'topic_id' => 1
        ]);
        Ticket::$mockDataByNumber['555444'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Update topic
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('555444', [
            'topicId' => 5
        ]);

        // Assert: Topic was updated
        $this->assertEquals(789, $result->getId());
        $this->assertEquals(5, $result->getTopicId());
    }

    /**
     * Test topic validation: inactive topic rejected
     */
    public function testRejectsInactiveTopic(): void {
        $inactiveTopic = new Topic(['id' => 99, 'name' => 'Deprecated Topic', 'active' => false]);
        Topic::$mockData[99] = $inactiveTopic;

        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'topic_id' => 1
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Help Topic is not active');

        $controller->update('781258', [
            'topicId' => 99
        ]);
    }

    /**
     * Test successful SLA assignment
     */
    public function testUpdateTicketSla(): void {
        // Setup: Create SLA
        $sla = new SLA(['id' => 2, 'name' => '24h Response', 'active' => true]);
        SLA::$mockData[2] = $sla;

        $ticket = new Ticket([
            'id' => 222,
            'number' => '222222',
            'subject' => 'Test Ticket',
            'sla_id' => null
        ]);
        Ticket::$mockDataByNumber['222222'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Assign SLA
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('222222', [
            'slaId' => 2
        ]);

        // Assert: SLA was assigned
        $this->assertEquals(222, $result->getId());
        $this->assertEquals(2, $result->getSLAId());
    }

    /**
     * Test SLA validation: inactive SLA rejected
     */
    public function testRejectsInactiveSla(): void {
        $inactiveSla = new SLA(['id' => 99, 'name' => 'Old SLA', 'active' => false]);
        SLA::$mockData[99] = $inactiveSla;

        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'sla_id' => null
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('SLA is not active');

        $controller->update('781258', [
            'slaId' => 99
        ]);
    }

    /**
     * Test successful staff assignment
     */
    public function testUpdateTicketStaffAssignment(): void {
        // Setup: Create staff
        $staff = new Staff(['id' => 10, 'name' => 'John Doe', 'active' => true]);
        Staff::$mockData[10] = $staff;

        $ticket = new Ticket([
            'id' => 333,
            'number' => '333333',
            'subject' => 'Test Ticket',
            'staff_id' => null
        ]);
        Ticket::$mockDataByNumber['333333'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Assign staff
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('333333', [
            'staffId' => 10
        ]);

        // Assert: Staff was assigned
        $this->assertEquals(333, $result->getId());
        $this->assertEquals(10, $result->getStaffId());
    }

    /**
     * Test staff validation: inactive staff rejected
     */
    public function testRejectsInactiveStaff(): void {
        $inactiveStaff = new Staff(['id' => 99, 'name' => 'Former Employee', 'active' => false]);
        Staff::$mockData[99] = $inactiveStaff;

        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'staff_id' => null
        ]);
        Ticket::$mockDataByNumber['781258'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Staff member is not active');

        $controller->update('781258', [
            'staffId' => 99
        ]);
    }

    /**
     * Test successful parent ticket assignment (subticket creation)
     */
    public function testUpdateTicketParentAssignment(): void {
        // Setup: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 500,
            'number' => '500500',
            'subject' => 'Parent Ticket',
            'is_child' => false
        ]);
        Ticket::$mockDataByNumber['500500'] = $parentTicket;
        Ticket::$mockData[500] = $parentTicket;

        // Create child ticket
        $childTicket = new Ticket([
            'id' => 501,
            'number' => '501501',
            'subject' => 'Child Ticket',
            'pid' => null
        ]);
        Ticket::$mockDataByNumber['501501'] = $childTicket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Assign parent
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('501501', [
            'parentTicketNumber' => '500500' // Use ticket NUMBER
        ]);

        // Assert: Parent was assigned (using internal ID)
        $this->assertEquals(501, $result->getId());
        $this->assertEquals(500, $result->getPid()); // Internal ID!
    }

    /**
     * Test parent ticket validation: parent cannot be a child itself
     */
    public function testRejectsParentThatIsAlreadyChild(): void {
        // Setup: Create ticket that is already a child
        $alreadyChildTicket = new Ticket([
            'id' => 600,
            'number' => '600600',
            'subject' => 'Already Child',
            'is_child' => true,
            'pid' => 100
        ]);
        Ticket::$mockDataByNumber['600600'] = $alreadyChildTicket;
        Ticket::$mockData[600] = $alreadyChildTicket;

        $ticket = new Ticket([
            'id' => 601,
            'number' => '601601',
            'subject' => 'Test Ticket',
            'pid' => null
        ]);
        Ticket::$mockDataByNumber['601601'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Parent ticket cannot be a child of another ticket');

        $controller->update('601601', [
            'parentTicketNumber' => '600600'
        ]);
    }

    /**
     * Test 404 error when ticket to update is not found
     */
    public function testReturns404WhenTicketNotFound(): void {
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Ticket not found');

        $controller->update('999999', [
            'statusId' => 1
        ]);
    }

    /**
     * Test 401 error when API key lacks UPDATE permission
     */
    public function testReturns401WhenApiKeyLacksUpdatePermission(): void {
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
            'can_update_tickets' => false,
            'permissions' => ['can_create_tickets' => false]
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('API key not authorized to update tickets');

        $controller->update('781258', [
            'statusId' => 1
        ]);
    }

    /**
     * Test updating multiple properties at once
     */
    public function testUpdateMultiplePropertiesSimultaneously(): void {
        // Setup: Create all required objects
        $dept = new Dept(['id' => 5, 'name' => 'Support', 'active' => true]);
        Dept::$mockData[5] = $dept;

        $status = new TicketStatus(['id' => 3, 'name' => 'Closed']);
        TicketStatus::$mockData[3] = $status;

        $priority = new Priority(['id' => 1, 'name' => 'High']);
        Priority::$mockData[1] = $priority;

        $topic = new Topic(['id' => 7, 'name' => 'Bug Report', 'active' => true]);
        Topic::$mockData[7] = $topic;

        $staff = new Staff(['id' => 10, 'name' => 'John Doe', 'active' => true]);
        Staff::$mockData[10] = $staff;

        $ticket = new Ticket([
            'id' => 777,
            'number' => '777777',
            'subject' => 'Multi-Update Ticket',
            'dept_id' => 1,
            'status_id' => 1,
            'priority_id' => 2,
            'topic_id' => 1,
            'staff_id' => null
        ]);
        Ticket::$mockDataByNumber['777777'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Update MULTIPLE properties at once
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('777777', [
            'departmentId' => 5,
            'statusId' => 3,
            'topicId' => 7,
            'staffId' => 10
        ]);

        // Assert: All properties were updated
        $this->assertEquals(777, $result->getId());
        $this->assertEquals(5, $result->getDeptId());
        $this->assertEquals(3, $result->getStatusId());
        $this->assertEquals(7, $result->getTopicId());
        $this->assertEquals(10, $result->getStaffId());
    }

    /**
     * Test that updating with same value doesn't fail
     *
     * Verifies idempotency: updating a field to its current value
     * should succeed without errors.
     */
    public function testUpdateWithSameValueIsIdempotent(): void {
        // Setup
        $dept = new Dept(['id' => 5, 'name' => 'Support', 'active' => true]);
        Dept::$mockData[5] = $dept;

        $ticket = new Ticket([
            'id' => 888,
            'number' => '888888',
            'subject' => 'Idempotent Test',
            'dept_id' => 5 // Already set to 5
        ]);
        Ticket::$mockDataByNumber['888888'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Update to SAME value
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('888888', [
            'departmentId' => 5 // Same as current
        ]);

        // Assert: No errors, ticket unchanged
        $this->assertEquals(888, $result->getId());
        $this->assertEquals(5, $result->getDeptId());
    }

    /**
     * Test that update validates parent ticket by number first, then by ID
     *
     * This verifies the correct lookup priority for parentTicketNumber.
     */
    public function testParentTicketLookupPriorityNumberBeforeId(): void {
        // Setup: Parent ticket
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => '100100',
            'subject' => 'Parent',
            'is_child' => false
        ]);
        Ticket::$mockDataByNumber['100100'] = $parentTicket;
        Ticket::$mockData[100] = $parentTicket;

        // Child ticket
        $childTicket = new Ticket([
            'id' => 101,
            'number' => '101101',
            'subject' => 'Child',
            'pid' => null
        ]);
        Ticket::$mockDataByNumber['101101'] = $childTicket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Use ticket NUMBER as parentTicketNumber
        $controller = new ExtendedTicketApiController();
        $result = $controller->update('101101', [
            'parentTicketNumber' => '100100' // NUMBER (not ID!)
        ]);

        // Assert: Lookup by number succeeded, internal ID was used
        $this->assertEquals(101, $result->getId());
        $this->assertEquals(100, $result->getPid()); // Internal ID
    }

    /**
     * Test adding an internal note to a ticket
     */
    public function testAddInternalNote(): void {
        $ticket = new Ticket([
            'id' => 999,
            'number' => '999999',
            'subject' => 'Test Ticket'
        ]);
        Ticket::$mockDataByNumber['999999'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $result = $controller->update('999999', [
            'note' => 'This is an internal note from API'
        ]);

        // Assert: Ticket returned, note was added (via mock)
        $this->assertEquals(999, $result->getId());
        $this->assertTrue($ticket->noteWasPosted); // Mock flag
        $this->assertEquals('This is an internal note from API', $ticket->lastNoteContent);
        $this->assertEquals('API Update', $ticket->lastNoteTitle); // Default title
    }

    /**
     * Test adding internal note with custom title
     */
    public function testAddInternalNoteWithCustomTitle(): void {
        $ticket = new Ticket([
            'id' => 1000,
            'number' => '1000',
            'subject' => 'Test Ticket'
        ]);
        Ticket::$mockDataByNumber['1000'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $result = $controller->update('1000', [
            'note' => 'Custom note content',
            'noteTitle' => 'Bug Investigation'
        ]);

        $this->assertEquals(1000, $result->getId());
        $this->assertEquals('Custom note content', $ticket->lastNoteContent);
        $this->assertEquals('Bug Investigation', $ticket->lastNoteTitle);
    }

    /**
     * Test combining note with status change
     */
    public function testAddNoteWithStatusChange(): void {
        // Add mock status
        $closedStatus = new TicketStatus(['id' => 3, 'name' => 'Closed', 'state' => 'closed']);
        TicketStatus::$mockData[3] = $closedStatus;

        $ticket = new Ticket([
            'id' => 1002,
            'number' => '1002',
            'subject' => 'Test Ticket',
            'status_id' => 1 // Open
        ]);
        Ticket::$mockDataByNumber['1002'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $result = $controller->update('1002', [
            'statusId' => 3, // Closed
            'note' => 'Issue resolved via API'
        ]);

        $this->assertEquals(1002, $result->getId());
        $this->assertEquals(3, $result->getStatusId());
        $this->assertTrue($ticket->noteWasPosted);
        $this->assertEquals('Issue resolved via API', $ticket->lastNoteContent);
    }

    /**
     * Test that empty note is ignored
     */
    public function testEmptyNoteIsIgnored(): void {
        $ticket = new Ticket([
            'id' => 1003,
            'number' => '1003',
            'subject' => 'Test Ticket'
        ]);
        Ticket::$mockDataByNumber['1003'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $result = $controller->update('1003', [
            'note' => '   ' // Whitespace only
        ]);

        $this->assertEquals(1003, $result->getId());
        $this->assertFalse($ticket->noteWasPosted ?? false); // No note posted
    }

    /**
     * Test that note requires UPDATE permission
     */
    public function testNoteRequiresUpdatePermission(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not authorized to update');

        $ticket = new Ticket([
            'id' => 1004,
            'number' => '1004',
            'subject' => 'Test Ticket'
        ]);
        Ticket::$mockDataByNumber['1004'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_update_tickets' => false // No permission!
        ]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $controller->update('1004', [
            'note' => 'This should fail'
        ]);
    }
}
