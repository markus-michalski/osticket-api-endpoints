<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: GET List Children Endpoint Tests
 *
 * Tests for GET /api/tickets-subtickets-list.php/{parentId}.json
 *
 * EXPECTATION: ALL TESTS MUST FAIL (RED PHASE)
 * Implementation comes in GREEN Phase
 *
 * API-Spec:
 * - GET /api/tickets-subtickets-list.php/{parentId}.json
 * - Returns array of child tickets (subtickets) for given parent
 * - Returns empty array if parent has no children
 * - Requires can_manage_subtickets permission
 * - Requires Subticket plugin available
 *
 * Response Format (With Children):
 * {
 *   "children": [
 *     {
 *       "ticket_id": 123,
 *       "number": "654321",
 *       "subject": "Child Ticket 1",
 *       "status": "Open"
 *     },
 *     {
 *       "ticket_id": 124,
 *       "number": "654322",
 *       "subject": "Child Ticket 2",
 *       "status": "Closed"
 *     }
 *   ]
 * }
 *
 * Response Format (No Children):
 * {
 *   "children": []
 * }
 */
class GetListChildrenTest extends TestCase {

    protected $controller;

    protected function setUp(): void {
        parent::setUp();

        // Reset mock data
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        TicketStatus::$mockData = [];
        SubticketPlugin::$mockEnabled = true;
    }

    protected function tearDown(): void {
        parent::tearDown();

        // Clean up
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        TicketStatus::$mockData = [];
        SubticketPlugin::$mockEnabled = true;
    }

    /**
     * HAPPY PATH: Returns children when exist
     *
     * Test Case:
     * - Parent ticket (ID: 100) has multiple child tickets (ID: 200, 201)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns array with "children" key
     * - Children array contains multiple tickets
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     */
    public function testReturnsChildrenWhenExist(): void {
        // Arrange: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Ticket',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: Create child tickets
        $childStatus = new TicketStatus([
            'id' => 1,
            'name' => 'Open'
        ]);
        TicketStatus::$mockData[1] = $childStatus;

        $child1 = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Child Ticket 1',
            'status_id' => 1,
            'pid' => 100,
        ]);
        Ticket::$mockData[200] = $child1;

        $child2 = new Ticket([
            'id' => 201,
            'number' => 'ABC201',
            'subject' => 'Child Ticket 2',
            'status_id' => 1,
            'pid' => 100,
        ]);
        Ticket::$mockData[201] = $child2;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin([200, 201]); // Returns child IDs

        // Act: Get list of children
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getList('ABC100');

        // Assert: Children returned
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('children', $result,
            'Result should have "children" key');

        $this->assertIsArray($result['children'],
            'Children should be an array');

        $this->assertCount(2, $result['children'],
            'Should return 2 children tickets');
    }

    /**
     * HAPPY PATH: Returns children with full details
     *
     * Test Case:
     * - Parent has children
     * - Children have all required fields
     *
     * Expected:
     * - Each child object contains: ticket_id, number, subject, status
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     */
    public function testReturnsChildrenWithFullDetails(): void {
        // Arrange: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Ticket',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: Create status
        $openStatus = new TicketStatus([
            'id' => 1,
            'name' => 'Open'
        ]);
        TicketStatus::$mockData[1] = $openStatus;

        $closedStatus = new TicketStatus([
            'id' => 2,
            'name' => 'Closed'
        ]);
        TicketStatus::$mockData[2] = $closedStatus;

        // Arrange: Create child tickets
        $child1 = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Child Ticket 1',
            'status_id' => 1,
            'pid' => 100,
        ]);
        Ticket::$mockData[200] = $child1;

        $child2 = new Ticket([
            'id' => 201,
            'number' => 'ABC201',
            'subject' => 'Child Ticket 2',
            'status_id' => 2,
            'pid' => 100,
        ]);
        Ticket::$mockData[201] = $child2;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin([200, 201]);

        // Act: Get list of children
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getList('ABC100');

        // Assert: First child contains all required fields
        $firstChild = $result['children'][0];

        $this->assertArrayHasKey('ticket_id', $firstChild,
            'Child should have ticket_id');

        $this->assertArrayHasKey('number', $firstChild,
            'Child should have number');

        $this->assertArrayHasKey('subject', $firstChild,
            'Child should have subject');

        $this->assertArrayHasKey('status', $firstChild,
            'Child should have status');

        // Assert: Field values are correct for first child
        $this->assertEquals(200, $firstChild['ticket_id'],
            'First child ticket_id should be 200');

        $this->assertEquals('ABC200', $firstChild['number'],
            'First child number should be ABC200');

        $this->assertEquals('Child Ticket 1', $firstChild['subject'],
            'First child subject should be "Child Ticket 1"');

        $this->assertEquals('Open', $firstChild['status'],
            'First child status should be "Open"');

        // Assert: Second child has different status
        $secondChild = $result['children'][1];

        $this->assertEquals(201, $secondChild['ticket_id'],
            'Second child ticket_id should be 201');

        $this->assertEquals('Closed', $secondChild['status'],
            'Second child status should be "Closed"');
    }

    /**
     * HAPPY PATH: Returns empty array when parent has no children
     *
     * Test Case:
     * - Parent ticket exists but has no child tickets
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns array with "children" => []
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     */
    public function testReturnsEmptyArrayWhenParentHasNoChildren(): void {
        // Arrange: Create parent ticket WITHOUT children
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Without Children',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available (returns empty array of children)
        $this->registerSubticketPlugin([]); // NO CHILDREN

        // Act: Get list of children
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getList('ABC100');

        // Assert: Empty children array
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('children', $result,
            'Result should have "children" key');

        $this->assertIsArray($result['children'],
            'Children should be an array');

        $this->assertEmpty($result['children'],
            'Children array should be empty when parent has no children');
    }

    /**
     * ERROR CASE: Returns 404 when parent ticket not found
     *
     * Test Case:
     * - Parent ticket ID does not exist
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 404
     * - Error message: "Parent ticket not found"
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     */
    public function testReturns404WhenParentTicketNotFound(): void {
        // Arrange: No ticket with ID 999
        // Ticket::$mockData is empty

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin([]);

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Parent ticket not found');

        // Act: Try to get children of non-existent parent
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getList('999'); // Non-existent ID
    }

    /**
     * ERROR CASE: Returns 403 when API key lacks permission
     *
     * Test Case:
     * - Parent ticket exists
     * - API key does NOT have can_manage_subtickets permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 403 (Forbidden)
     * - Error message: "API key not authorized for subticket operations"
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     */
    public function testReturns403WhenApiKeyLacksPermission(): void {
        // Arrange: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Ticket',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: API key WITHOUT subticket permission
        $apiKey = new API([
            'key' => 'test-key-no-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => false, // NO PERMISSION
            ]
        ]);

        // Arrange: Plugin available
        $this->registerSubticketPlugin([]);

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('API key not authorized for subticket operations');

        // Act: Try to get children without permission
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getList('ABC100');
    }

    /**
     * ERROR CASE: Returns 501 when plugin not available
     *
     * Test Case:
     * - Parent ticket exists
     * - API key has permission
     * - Subticket plugin is NOT available/installed
     *
     * Expected:
     * - Throws Exception with code 501 (Not Implemented)
     * - Error message: "Subticket plugin not available"
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     */
    public function testReturns501WhenPluginNotAvailable(): void {
        // Arrange: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Ticket',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: NO plugin available
        SubticketPlugin::$mockEnabled = false;

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Act: Try to get children without plugin
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getList('ABC100');
    }

    /**
     * EDGE CASE: Handles invalid ticket ID
     *
     * Test Case:
     * - Ticket ID is invalid (0, negative)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 400 (Bad Request)
     * - Error message: "Invalid ticket ID"
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     */
    public function testHandlesInvalidTicketId(): void {
        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPlugin([]);

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid ticket number');

        // Act: Try to get children with invalid ID (0 is considered invalid)
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->getList(0); // Invalid: ID must be > 0
    }

    /**
     * EDGE CASE: Handles orphaned child references
     *
     * Test Case:
     * - Parent ticket exists
     * - Plugin returns child IDs but child tickets don't exist in database
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns only valid children (skips orphaned references)
     * - OR returns empty array if all children are orphaned
     *
     * Implementation Method: SubticketApiController->getList(int $parentId): array
     *
     * Note: This tests data integrity - what happens when plugin returns IDs
     * that point to non-existent tickets
     */
    public function testHandlesOrphanedChildReferences(): void {
        // Arrange: Create parent ticket
        $parentTicket = new Ticket([
            'id' => 100,
            'number' => 'ABC100',
            'subject' => 'Parent Ticket',
            'status_id' => 1,
        ]);
        Ticket::$mockData[100] = $parentTicket;

        // Arrange: Create only ONE valid child
        $validChild = new Ticket([
            'id' => 200,
            'number' => 'ABC200',
            'subject' => 'Valid Child',
            'status_id' => 1,
            'pid' => 100,
        ]);
        Ticket::$mockData[200] = $validChild;

        $validStatus = new TicketStatus([
            'id' => 1,
            'name' => 'Open'
        ]);
        TicketStatus::$mockData[1] = $validStatus;

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin returns 3 children, but only 1 exists
        // IDs 201 and 202 are orphaned (don't exist in Ticket::$mockData)
        $this->registerSubticketPlugin([200, 201, 202]);

        // Act: Get list of children
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->getList('ABC100');

        // Assert: Should handle gracefully (skip orphaned references)
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('children', $result,
            'Result should have "children" key');

        // Only 1 valid child should be returned (orphaned references skipped)
        $this->assertCount(1, $result['children'],
            'Should return only valid children (skip orphaned references)');

        $this->assertEquals(200, $result['children'][0]['ticket_id'],
            'Returned child should be the valid one (ID 200)');
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
     * @param array $childIds Array of child ticket IDs to return
     * @return void
     */
    private function registerSubticketPlugin(array $childIds = []): void {
        $mockPlugin = new class($childIds) {
            private $childIds;

            public function __construct($childIds) {
                $this->childIds = $childIds;
            }

            public function isActive() { return true; }
            public function getName() { return 'Subticket Plugin'; }

            // Mock method for getChildren (will be used in implementation)
            public function getChildren($parentTicket) {
                return $this->childIds;
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
