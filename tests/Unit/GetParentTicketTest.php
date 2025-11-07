<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: GET Parent Ticket Endpoint Tests
 *
 * Tests für GET /api/tickets-subtickets-parent.php/{childId}.json
 *
 * EXPECTATION: ALL TESTS MUST FAIL (RED PHASE)
 * Implementation kommt in GREEN Phase
 *
 * API-Spec:
 * - GET /api/tickets-subtickets-parent.php/{childId}.json
 * - Returns parent ticket data or null if no parent
 * - Requires can_manage_subtickets permission
 * - Requires Subticket plugin available
 *
 * Response Format (Success):
 * {
 *   "parent": {
 *     "ticket_id": 100,
 *     "number": "ABC100",
 *     "subject": "Parent Ticket",
 *     "status": "Open"
 *   }
 * }
 *
 * Response Format (No Parent):
 * {
 *   "parent": null
 * }
 */
class GetParentTicketTest extends TestCase {

    protected $controller;

    protected function setUp(): void {
        parent::setUp();

        // Reset mock data
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        TicketStatus::$mockData = [];
    }

    protected function tearDown(): void {
        parent::tearDown();

        // Clean up
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        TicketStatus::$mockData = [];
    }

    /**
     * HAPPY PATH: Returns parent ticket when exists
     *
     * Test Case:
     * - Child ticket (ID: 200) has parent (ID: 100)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns array with "parent" key
     * - Parent contains ticket data
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     */
    public function testReturnsParentTicketWhenExists(): void {
        // Arrange: Create parent ticket
        $parentStatus = new TicketStatus([
            'id' => 1,
            'name' => 'Open'
        ]);
        TicketStatus::$mockData[1] = $parentStatus;

        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Ticket',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: Create child ticket with parent reference
        $childTicket = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Child Ticket',
            'status_id' => 1,
            'pid' => 100, // Parent ID
        ]);
        Ticket::$mockData[200] = $childTicket;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin();

        // Act: Get parent ticket
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getParent(200);

        // Assert: Parent returned
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('parent', $result,
            'Result should have "parent" key');

        $this->assertNotNull($result['parent'],
            'Parent should not be null when ticket has a parent');
    }

    /**
     * HAPPY PATH: Returns parent with full details
     *
     * Test Case:
     * - Child ticket has parent
     * - Parent has all required fields
     *
     * Expected:
     * - Parent object contains: ticket_id, number, subject, status
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     */
    public function testReturnsParentWithFullDetails(): void {
        // Arrange: Create status
        $parentStatus = new TicketStatus([
            'id' => 1,
            'name' => 'Open'
        ]);
        TicketStatus::$mockData[1] = $parentStatus;

        // Arrange: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Ticket',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: Create child ticket
        $childTicket = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Child Ticket',
            'status_id' => 1,
            'pid' => 100,
        ]);
        Ticket::$mockData[200] = $childTicket;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin();

        // Act: Get parent ticket
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getParent(200);

        // Assert: Parent contains all required fields
        $parent = $result['parent'];

        $this->assertArrayHasKey('ticket_id', $parent,
            'Parent should have ticket_id');

        $this->assertArrayHasKey('number', $parent,
            'Parent should have number');

        $this->assertArrayHasKey('subject', $parent,
            'Parent should have subject');

        $this->assertArrayHasKey('status', $parent,
            'Parent should have status');

        // Assert: Field values are correct
        $this->assertEquals(100, $parent['ticket_id'],
            'Parent ticket_id should be 100');

        $this->assertEquals('ABC100', $parent['number'],
            'Parent number should be ABC100');

        $this->assertEquals('Parent Ticket', $parent['subject'],
            'Parent subject should be "Parent Ticket"');

        $this->assertEquals('Open', $parent['status'],
            'Parent status should be "Open"');
    }

    /**
     * HAPPY PATH: Returns null when ticket has no parent
     *
     * Test Case:
     * - Ticket exists but has no parent (pid = null)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns array with "parent" => null
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     */
    public function testReturnsNullWhenTicketHasNoParent(): void {
        // Arrange: Create ticket WITHOUT parent
        $ticket = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Standalone Ticket',
            'status_id' => 1,
            'pid' => null, // NO PARENT
        ]);
        Ticket::$mockData[200] = $ticket;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin();

        // Act: Get parent ticket
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getParent(200);

        // Assert: Parent is null
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('parent', $result,
            'Result should have "parent" key');

        $this->assertNull($result['parent'],
            'Parent should be null when ticket has no parent');
    }

    /**
     * ERROR CASE: Returns 404 when ticket not found
     *
     * Test Case:
     * - Ticket ID does not exist
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 404
     * - Error message: "Ticket not found"
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     */
    public function testReturns404WhenTicketNotFound(): void {
        // Arrange: No ticket with ID 999
        // Ticket::$mockData is empty

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Ticket not found');

        // Act: Try to get parent of non-existent ticket
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getParent(999); // Non-existent ID
    }

    /**
     * ERROR CASE: Returns 401 when API key lacks permission
     *
     * Test Case:
     * - Ticket exists
     * - API key does NOT have can_manage_subtickets permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 401 (Unauthorized)
     * - Error message: "API key not authorized for subticket operations"
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     */
    public function testReturns401WhenApiKeyLacksPermission(): void {
        // Arrange: Create ticket
        $ticket = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Child Ticket',
            'status_id' => 1,
            'pid' => 100,
        ]);
        Ticket::$mockData[200] = $ticket;

        // Arrange: API key WITHOUT subticket permission
        $apiKey = new API([
            'key' => 'test-key-no-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => false, // NO PERMISSION
            ]
        ]);

        // Arrange: Plugin available
        $this->registerSubticketPlugin();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403); // 403 Forbidden (not 401 Unauthorized)
        $this->expectExceptionMessage('API key not authorized for subticket operations');

        // Act: Try to get parent without permission
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getParent(200);
    }

    /**
     * ERROR CASE: Returns 501 when plugin not available
     *
     * Test Case:
     * - Ticket exists
     * - API key has permission
     * - Subticket plugin is NOT available/installed
     *
     * Expected:
     * - Throws Exception with code 501 (Not Implemented)
     * - Error message: "Subticket plugin not available"
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     */
    public function testReturns501WhenPluginNotAvailable(): void {
        // Arrange: Create ticket
        $ticket = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Child Ticket',
            'status_id' => 1,
            'pid' => 100,
        ]);
        Ticket::$mockData[200] = $ticket;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: NO plugin available
        PluginManager::getInstance()->registerPlugin('subticket', null);

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Act: Try to get parent without plugin
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getParent(200);
    }

    /**
     * EDGE CASE: Handles invalid ticket ID
     *
     * Test Case:
     * - Ticket ID is invalid (non-numeric string, negative, etc.)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 400 (Bad Request)
     * - Error message: "Invalid ticket ID"
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     *
     * Note: In PHP 7.4+, type declaration "int" will auto-convert strings to int,
     * but we should still test validation logic for invalid inputs.
     */
    public function testHandlesInvalidTicketId(): void {
        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid ticket ID');

        // Act: Try to get parent with invalid ID (0 is considered invalid)
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getParent(0); // Invalid: ID must be > 0
    }

    /**
     * EDGE CASE: Parent ticket exists but is inaccessible
     *
     * Test Case:
     * - Child ticket exists with pid set
     * - Parent ticket does NOT exist in database (orphaned reference)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns array with "parent" => null
     * - OR throws Exception with code 404 "Parent ticket not found"
     *
     * Implementation Method: SubticketApiController->getParent(int $childId): array
     *
     * Note: This tests data integrity - what happens when pid points to non-existent ticket
     */
    public function testHandlesOrphanedParentReference(): void {
        // Arrange: Create child ticket with parent reference
        $childTicket = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Orphaned Child Ticket',
            'status_id' => 1,
            'pid' => 100, // Parent ID set
        ]);
        Ticket::$mockData[200] = $childTicket;

        // Arrange: Parent ticket does NOT exist (Ticket::$mockData[100] not set)
        // This simulates orphaned reference (data integrity issue)

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin();

        // Act: Get parent ticket
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getParent(200);

        // Assert: Should handle gracefully (return null)
        // When parent reference is orphaned, plugin returns null gracefully
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('parent', $result,
            'Result should have "parent" key');

        $this->assertNull($result['parent'],
            'Parent should be null when parent ticket does not exist (orphaned reference)');
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Create API key with subticket permission
     *
     * @return API API key object
     */
    private function createApiKeyWithPermission(): API {
        $apiKey = new API([
            'key' => 'test-key-with-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => true,
            ]
        ]);

        API::$mockData['test-key-with-permission'] = $apiKey;

        return $apiKey;
    }

    /**
     * Register active Subticket plugin in PluginManager
     *
     * @return void
     */
    private function registerSubticketPlugin(): void {
        $mockPlugin = new class {
            public function isActive() { return true; }
            public function getName() { return 'Subticket Plugin'; }

            // Mock method for getParent (will be used in implementation)
            public function getParent($childTicket) {
                $parentId = $childTicket->getPid();
                if (!$parentId) {
                    return null;
                }
                return Ticket::lookup($parentId);
            }
        };

        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);
    }

    /**
     * Create SubticketApiController instance
     *
     * @return SubticketApiController Controller instance
     */
    private function createSubticketController() {
        // This will fail in RED phase if method doesn't exist yet
        require_once __DIR__ . '/../../controllers/SubticketApiController.php';
        return new SubticketApiController();
    }
}
