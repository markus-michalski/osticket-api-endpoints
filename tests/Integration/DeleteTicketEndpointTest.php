<?php

use PHPUnit\Framework\TestCase;

// Load the controller
require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';

/**
 * Integration Tests for DELETE /api/tickets-delete.php endpoint
 *
 * RED PHASE: These tests are written FIRST and MUST FAIL initially!
 *
 * Tests the complete workflow of deleting tickets via API including:
 * - Deleting ticket from ost_ticket table
 * - Cascading delete of custom data from ost_ticket__cdata table
 * - Cascading delete of thread entries from ost_thread_entry table
 * - Removing ticket_pid from child tickets if parent is deleted
 * - Permission checking (can_delete_tickets)
 * - Error handling (404, 401)
 * - Database cleanup verification
 *
 * The deleteTicket() method accepts ticket number as parameter:
 * - ticketNumber: Ticket number to delete (e.g., "781258")
 *
 * Returns:
 * - 200 OK on successful deletion
 * - 404 Not Found if ticket doesn't exist
 * - 401 Unauthorized if API key lacks can_delete_tickets permission
 */
class DeleteTicketEndpointTest extends TestCase {

    protected function setUp(): void {
        // Reset all mock data before each test
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        API::$mockData = [];
        Thread::$mockData = [];
        ThreadEntry::$mockData = [];
    }

    /**
     * Test successful ticket deletion
     *
     * Verifies that a ticket can be deleted and is removed from storage
     */
    public function testDeleteTicketSuccessfully(): void {
        // Setup: Create ticket
        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket to Delete',
            'dept_id' => 1
        ]);
        Ticket::$mockData[123] = $ticket;
        Ticket::$mockDataByNumber['781258'] = $ticket;

        // Setup API key with DELETE permission
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Delete ticket
        $controller = new ExtendedTicketApiController();
        $result = $controller->deleteTicket('781258');

        // Assert: Deletion was successful (returns true or ticket number)
        $this->assertTrue($result === true || $result === '781258');

        // Assert: Ticket is removed from mock storage
        $this->assertNull(Ticket::lookupByNumber('781258'));
        $this->assertNull(Ticket::lookup(123));
    }

    /**
     * Test deleting ticket with thread entries (cascading delete)
     *
     * Verifies that all thread entries are deleted when ticket is deleted
     */
    public function testDeleteTicketWithThreadEntries(): void {
        // Setup: Create ticket with thread entries
        $threadEntry1 = new ThreadEntry([
            'id' => 1,
            'type' => 'M',
            'poster' => 'User',
            'body' => 'Initial message',
            'ticket_id' => 456
        ]);

        $threadEntry2 = new ThreadEntry([
            'id' => 2,
            'type' => 'R',
            'poster' => 'Staff',
            'body' => 'Response message',
            'ticket_id' => 456,
            'staff_id' => 10
        ]);

        $thread = new Thread([$threadEntry1, $threadEntry2]);

        $ticket = new Ticket([
            'id' => 456,
            'number' => '999888',
            'subject' => 'Ticket with Thread',
            'dept_id' => 1,
            'thread' => $thread
        ]);
        Ticket::$mockData[456] = $ticket;
        Ticket::$mockDataByNumber['999888'] = $ticket;

        // Store thread entries in mock storage
        ThreadEntry::$mockData[1] = $threadEntry1;
        ThreadEntry::$mockData[2] = $threadEntry2;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Delete ticket
        $controller = new ExtendedTicketApiController();
        $result = $controller->deleteTicket('999888');

        // Assert: Deletion was successful
        $this->assertTrue($result === true || $result === '999888');

        // Assert: Ticket is removed
        $this->assertNull(Ticket::lookupByNumber('999888'));

        // Assert: Thread entries are removed (cascading delete)
        $this->assertNull(ThreadEntry::lookup(1));
        $this->assertNull(ThreadEntry::lookup(2));
    }

    /**
     * Test deleting parent ticket removes ticket_pid from children
     *
     * Verifies that when a parent ticket is deleted, all child tickets
     * have their ticket_pid set to NULL (no orphan references)
     */
    public function testDeleteParentTicketRemovesTicketPidFromChildren(): void {
        // Setup: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 500,
            'number' => '500500',
            'subject' => 'Parent Ticket',
            'dept_id' => 1,
            'is_child' => false
        ]);
        Ticket::$mockData[500] = $parentTicket;
        Ticket::$mockDataByNumber['500500'] = $parentTicket;

        // Setup: Create child tickets
        $childTicket1 = new Ticket([
            'id' => 501,
            'number' => '501501',
            'subject' => 'Child Ticket 1',
            'dept_id' => 1,
            'pid' => 500  // Parent ID
        ]);
        Ticket::$mockData[501] = $childTicket1;
        Ticket::$mockDataByNumber['501501'] = $childTicket1;

        $childTicket2 = new Ticket([
            'id' => 502,
            'number' => '502502',
            'subject' => 'Child Ticket 2',
            'dept_id' => 1,
            'pid' => 500  // Parent ID
        ]);
        Ticket::$mockData[502] = $childTicket2;
        Ticket::$mockDataByNumber['502502'] = $childTicket2;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Delete parent ticket
        $controller = new ExtendedTicketApiController();
        $result = $controller->deleteTicket('500500');

        // Assert: Deletion was successful
        $this->assertTrue($result === true || $result === '500500');

        // Assert: Parent is removed
        $this->assertNull(Ticket::lookupByNumber('500500'));

        // Assert: Children still exist BUT ticket_pid is NULL
        $this->assertNotNull(Ticket::lookupByNumber('501501'));
        $this->assertNotNull(Ticket::lookupByNumber('502502'));
        $this->assertNull(Ticket::lookup(501)->getPid());
        $this->assertNull(Ticket::lookup(502)->getPid());
    }

    /**
     * Test deleting non-existent ticket returns 404
     *
     * Verifies proper error handling when ticket doesn't exist
     */
    public function testDeleteNonExistentTicketReturns404(): void {
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert: Expect 404 exception
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Ticket not found');

        $controller->deleteTicket('999999');
    }

    /**
     * Test 401 error when API key lacks DELETE permission
     *
     * Verifies that only API keys with can_delete_tickets permission
     * can delete tickets
     */
    public function testReturns401WhenApiKeyLacksDeletePermission(): void {
        // Setup: Create ticket
        $ticket = new Ticket([
            'id' => 123,
            'number' => '781258',
            'subject' => 'Test Ticket',
            'dept_id' => 1
        ]);
        Ticket::$mockData[123] = $ticket;
        Ticket::$mockDataByNumber['781258'] = $ticket;

        // Setup API key WITHOUT DELETE permission
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => false,
            'can_read_tickets' => true,
            'can_update_tickets' => true,
            'permissions' => ['can_create_tickets' => true]
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert: Expect 401 exception
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('API key not authorized to delete tickets');

        $controller->deleteTicket('781258');
    }

    /**
     * Test deleting ticket with custom data (ost_ticket__cdata)
     *
     * Verifies that custom field data is deleted along with the ticket
     */
    public function testDeleteTicketWithCustomData(): void {
        // Setup: Create ticket with custom data
        $customData = [
            'custom_field_1' => 'Custom Value 1',
            'custom_field_2' => 'Custom Value 2'
        ];

        $ticket = new Ticket([
            'id' => 789,
            'number' => '555444',
            'subject' => 'Ticket with Custom Data',
            'dept_id' => 1,
            'custom_data' => $customData
        ]);
        Ticket::$mockData[789] = $ticket;
        Ticket::$mockDataByNumber['555444'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Delete ticket
        $controller = new ExtendedTicketApiController();
        $result = $controller->deleteTicket('555444');

        // Assert: Deletion was successful
        $this->assertTrue($result === true || $result === '555444');

        // Assert: Ticket is removed (including custom data)
        $this->assertNull(Ticket::lookupByNumber('555444'));
    }

    /**
     * Test deleting ticket by ID (fallback behavior)
     *
     * Verifies that deletion works with ticket ID as well as number
     */
    public function testDeleteTicketByIdAsFallback(): void {
        // Setup: Create ticket
        $ticket = new Ticket([
            'id' => 111,
            'number' => '111111',
            'subject' => 'Test Ticket',
            'dept_id' => 1
        ]);
        Ticket::$mockData[111] = $ticket;
        Ticket::$mockDataByNumber['111111'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Delete by ID (not number)
        $controller = new ExtendedTicketApiController();
        $result = $controller->deleteTicket('111');  // Use ID instead of number

        // Assert: REFACTOR PHASE - Always returns ticket NUMBER (not ID)
        $this->assertEquals('111111', $result);

        // Assert: Ticket is removed
        $this->assertNull(Ticket::lookup(111));
    }

    /**
     * Test that deletion is idempotent (deleting twice doesn't fail catastrophically)
     *
     * Verifies that attempting to delete an already deleted ticket
     * returns proper error (404) instead of crashing
     */
    public function testDeletionIsIdempotent(): void {
        // Setup: Create ticket
        $ticket = new Ticket([
            'id' => 222,
            'number' => '222222',
            'subject' => 'Test Ticket',
            'dept_id' => 1
        ]);
        Ticket::$mockData[222] = $ticket;
        Ticket::$mockDataByNumber['222222'] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();

        // First deletion: Should succeed
        $result1 = $controller->deleteTicket('222222');
        $this->assertTrue($result1 === true || $result1 === '222222');

        // Second deletion: Should return 404 (not catastrophic failure)
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Ticket not found');

        $controller->deleteTicket('222222');
    }

    /**
     * Test that child tickets can be deleted (they are not protected)
     *
     * Verifies that child tickets (with ticket_pid set) can be deleted normally
     */
    public function testDeleteChildTicketSucceeds(): void {
        // Setup: Create parent
        $parentTicket = new Ticket([
            'id' => 300,
            'number' => '300300',
            'subject' => 'Parent Ticket',
            'dept_id' => 1,
            'is_child' => false
        ]);
        Ticket::$mockData[300] = $parentTicket;
        Ticket::$mockDataByNumber['300300'] = $parentTicket;

        // Setup: Create child
        $childTicket = new Ticket([
            'id' => 301,
            'number' => '301301',
            'subject' => 'Child Ticket',
            'dept_id' => 1,
            'pid' => 300
        ]);
        Ticket::$mockData[301] = $childTicket;
        Ticket::$mockDataByNumber['301301'] = $childTicket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => true
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute: Delete child ticket
        $controller = new ExtendedTicketApiController();
        $result = $controller->deleteTicket('301301');

        // Assert: Deletion was successful
        $this->assertTrue($result === true || $result === '301301');

        // Assert: Child is removed
        $this->assertNull(Ticket::lookupByNumber('301301'));

        // Assert: Parent still exists
        $this->assertNotNull(Ticket::lookupByNumber('300300'));
    }

    /**
     * Test backward compatibility: canCreateTickets permission is NOT accepted
     *
     * Verifies that DELETE requires explicit can_delete_tickets permission
     * (stricter than UPDATE which accepts can_create_tickets as fallback)
     */
    public function testDeleteRequiresExplicitDeletePermission(): void {
        // Setup: Create ticket
        $ticket = new Ticket([
            'id' => 333,
            'number' => '333333',
            'subject' => 'Test Ticket',
            'dept_id' => 1
        ]);
        Ticket::$mockData[333] = $ticket;
        Ticket::$mockDataByNumber['333333'] = $ticket;

        // Setup API key with CREATE permission but NOT DELETE
        $apiKey = new API([
            'key' => 'test-api-key',
            'can_delete_tickets' => false,
            'permissions' => ['can_create_tickets' => true]
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute & Assert: DELETE should FAIL (no backward compat)
        $controller = new ExtendedTicketApiController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('API key not authorized to delete tickets');

        $controller->deleteTicket('333333');
    }
}
