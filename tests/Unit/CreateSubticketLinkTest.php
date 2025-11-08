<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: POST Create Subticket Link Endpoint Tests
 *
 * Tests für POST /api/tickets-subtickets-create.php
 *
 * EXPECTATION: ALL TESTS MUST FAIL (RED PHASE)
 * Implementation kommt in GREEN Phase
 *
 * API-Spec:
 * - POST /api/tickets-subtickets-create.php
 * - Body: {"parent_id": 100, "child_id": 200}
 * - Creates parent-child relationship between tickets
 * - Requires can_manage_subtickets permission
 * - Requires Subticket plugin available
 *
 * Response Format (Success):
 * {
 *   "success": true,
 *   "message": "Subticket relationship created successfully",
 *   "parent": {
 *     "ticket_id": 100,
 *     "number": "ABC100",
 *     "subject": "Parent Ticket",
 *     "status": "Open"
 *   },
 *   "child": {
 *     "ticket_id": 200,
 *     "number": "ABC200",
 *     "subject": "Child Ticket",
 *     "status": "Open"
 *   }
 * }
 */
class CreateSubticketLinkTest extends TestCase {

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
     * HAPPY PATH: Creates subticket relationship successfully
     *
     * Test Case:
     * - Parent ticket (ID: 100) exists
     * - Child ticket (ID: 200) exists
     * - Child has no existing parent
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns array with "success" => true
     * - Returns "message" with success text
     * - Returns "parent" with ticket data
     * - Returns "child" with ticket data
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testCreatesSubticketRelationshipSuccessfully(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create parent ticket
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: Create child ticket (no parent yet)
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available with createLink() method
        $plugin = $this->registerSubticketPluginWithCreateLink();

        // Act: Create subticket link
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->createLink('ABC100', 'ABC200');

        // Assert: Success response
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('success', $result,
            'Result should have "success" key');

        $this->assertTrue($result['success'],
            'Success should be true');

        $this->assertArrayHasKey('message', $result,
            'Result should have "message" key');

        $this->assertStringContainsString('successfully', strtolower($result['message']),
            'Message should contain "successfully"');
    }

    /**
     * HAPPY PATH: Returns parent and child data after creation
     *
     * Test Case:
     * - Subticket link created successfully
     * - Parent and child tickets exist
     *
     * Expected:
     * - Response contains "parent" key with full ticket data
     * - Response contains "child" key with full ticket data
     * - Both parent and child have: ticket_id, number, subject, status
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturnsParentAndChildDataAfterCreation(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create parent ticket
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: Create child ticket
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Act: Create subticket link
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->createLink('ABC100', 'ABC200');

        // Assert: Parent data
        $this->assertArrayHasKey('parent', $result,
            'Result should have "parent" key');

        $parent = $result['parent'];
        $this->assertArrayHasKey('ticket_id', $parent,
            'Parent should have ticket_id');
        $this->assertEquals(100, $parent['ticket_id'],
            'Parent ticket_id should be 100');
        $this->assertArrayHasKey('number', $parent,
            'Parent should have number');
        $this->assertEquals('ABC100', $parent['number'],
            'Parent number should be ABC100');
        $this->assertArrayHasKey('subject', $parent,
            'Parent should have subject');
        $this->assertEquals('Parent Ticket', $parent['subject'],
            'Parent subject should be "Parent Ticket"');
        $this->assertArrayHasKey('status', $parent,
            'Parent should have status');
        $this->assertEquals('Open', $parent['status'],
            'Parent status should be "Open"');

        // Assert: Child data
        $this->assertArrayHasKey('child', $result,
            'Result should have "child" key');

        $child = $result['child'];
        $this->assertArrayHasKey('ticket_id', $child,
            'Child should have ticket_id');
        $this->assertEquals(200, $child['ticket_id'],
            'Child ticket_id should be 200');
        $this->assertArrayHasKey('number', $child,
            'Child should have number');
        $this->assertEquals('ABC200', $child['number'],
            'Child number should be ABC200');
        $this->assertArrayHasKey('subject', $child,
            'Child should have subject');
        $this->assertEquals('Child Ticket', $child['subject'],
            'Child subject should be "Child Ticket"');
        $this->assertArrayHasKey('status', $child,
            'Child should have status');
        $this->assertEquals('Open', $child['status'],
            'Child status should be "Open"');
    }

    /**
     * HAPPY PATH: Creates link via plugin
     *
     * Test Case:
     * - Verify that plugin->createLink() is called with correct parameters
     *
     * Expected:
     * - Plugin->createLink($parentTicket, $childTicket) is invoked
     * - Link creation succeeds without exception
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testCreatesLinkViaPlugin(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create parent ticket
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: Create child ticket
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Reset call log before test
        SubticketPlugin::resetCallLog();

        // Act: Create subticket link
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->createLink('ABC100', 'ABC200');

        // Assert: Plugin was called
        $this->assertCount(1, SubticketPlugin::$callLog['linkTicket'] ?? [],
            'Plugin linkTicket should be called exactly once');

        $linkCalls = SubticketPlugin::$callLog['linkTicket'];
        $this->assertEquals(200, $linkCalls[0]['childId'],
            'Plugin should be called with child ID 200');

        $this->assertEquals(100, $linkCalls[0]['parentId'],
            'Plugin should be called with parent ID 100');
    }

    /**
     * ERROR CASE: Returns 404 when parent not found
     *
     * Test Case:
     * - Parent ticket ID does not exist
     * - Child ticket exists
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 404
     * - Error message: "Parent ticket not found"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns404WhenParentNotFound(): void {
        // Arrange: No parent ticket (ID 999 does not exist)

        // Arrange: Create child ticket
        $status = $this->createMockStatus();
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Parent ticket not found');

        // Act: Try to create link with non-existent parent
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink(999, 200); // Parent 999 does not exist
    }

    /**
     * ERROR CASE: Returns 404 when child not found
     *
     * Test Case:
     * - Parent ticket exists
     * - Child ticket ID does not exist
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 404
     * - Error message: "Child ticket not found"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns404WhenChildNotFound(): void {
        // Arrange: Create parent ticket
        $status = $this->createMockStatus();
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: No child ticket (ID 999 does not exist)

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Child ticket not found');

        // Act: Try to create link with non-existent child
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink('ABC100', '999'); // Parent exists, child does not
    }

    /**
     * ERROR CASE: Returns 403 when API key lacks permission
     *
     * Test Case:
     * - Parent and child tickets exist
     * - API key does NOT have can_manage_subtickets permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 403 (Forbidden)
     * - Error message: "API key not authorized for subticket operations"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns403WhenApiKeyLacksPermission(): void {
        // Arrange: Create tickets
        $status = $this->createMockStatus();
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1);

        // Arrange: API key WITHOUT subticket permission
        $apiKey = new API([
            'key' => 'test-key-no-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => false, // NO PERMISSION
            ]
        ]);

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('API key not authorized for subticket operations');

        // Act: Try to create link without permission
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink('ABC100', 'ABC200');
    }

    /**
     * ERROR CASE: Returns 501 when plugin not available
     *
     * Test Case:
     * - Parent and child tickets exist
     * - API key has permission
     * - Subticket plugin is NOT available/installed
     *
     * Expected:
     * - Throws Exception with code 501 (Not Implemented)
     * - Error message: "Subticket plugin not available"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns501WhenPluginNotAvailable(): void {
        // Arrange: Create tickets
        $status = $this->createMockStatus();
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: NO plugin available
        SubticketPlugin::$mockEnabled = false;

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Act: Try to create link without plugin
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink('ABC100', 'ABC200');
    }

    /**
     * ERROR CASE: Returns 400 when parent ID invalid
     *
     * Test Case:
     * - Parent ID is <= 0 (invalid)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 400 (Bad Request)
     * - Error message: "Invalid parent ticket ID"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns400WhenParentIdInvalid(): void {
        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid parent ticket number');

        // Act: Try to create link with invalid parent ID
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink(0, 200); // Invalid: ID must be > 0
    }

    /**
     * ERROR CASE: Returns 400 when child ID invalid
     *
     * Test Case:
     * - Child ID is <= 0 (invalid)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 400 (Bad Request)
     * - Error message: "Invalid child ticket ID"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns400WhenChildIdInvalid(): void {
        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid child ticket number');

        // Act: Try to create link with invalid child ID
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink('ABC100', '0'); // Invalid: ID must be > 0
    }

    /**
     * ERROR CASE: Returns 400 when parent equals child
     *
     * Test Case:
     * - Parent ID == Child ID (same ticket)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 400 (Bad Request)
     * - Error message: "Cannot link ticket to itself"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns400WhenParentEqualsChild(): void {
        // Arrange: Create ticket
        $status = $this->createMockStatus();
        $ticket = $this->createMockTicket(100, 'ABC100', 'Ticket', 1);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithCreateLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Cannot link ticket to itself');

        // Act: Try to create link with same parent and child
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink('ABC100', 'ABC100'); // Same ticket
    }

    /**
     * ERROR CASE: Returns 409 when relationship already exists
     *
     * Test Case:
     * - Parent and child tickets exist
     * - Parent-child relationship already exists
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 409 (Conflict)
     * - Error message: "Subticket relationship already exists"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns409WhenRelationshipAlreadyExists(): void {
        // Arrange: Create tickets
        $status = $this->createMockStatus();
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 100); // Already has parent 100

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available with hasParent check
        $plugin = $this->registerSubticketPluginWithHasParent(true, 100);

        // Expect Exception (422 instead of 409 because osTicket's Http::response() doesn't support 409)
        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('Subticket relationship already exists');

        // Act: Try to create link when relationship already exists
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink('ABC100', 'ABC200');
    }

    /**
     * ERROR CASE: Returns 409 when child already has different parent
     *
     * Test Case:
     * - Parent ticket (ID: 100) exists
     * - Child ticket (ID: 200) exists
     * - Child already has a different parent (ID: 150)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 409 (Conflict)
     * - Error message: "Child ticket already has a different parent"
     *
     * Implementation Method: SubticketApiController->createLink(int $parentId, int $childId): array
     */
    public function testReturns409WhenChildAlreadyHasParent(): void {
        // Arrange: Create tickets
        $status = $this->createMockStatus();
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);
        $existingParent = $this->createMockTicket(150, 'ABC150', 'Existing Parent', 1);
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 150); // Already has parent 150

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available with hasParent check (different parent)
        $plugin = $this->registerSubticketPluginWithHasParent(true, 150);

        // Expect Exception (422 instead of 409 because osTicket's Http::response() doesn't support 409)
        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('Child ticket already has a different parent');

        // Act: Try to create link when child has different parent
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->createLink('ABC100', 'ABC200');
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Create mock ticket status
     *
     * @param int $id Status ID (default: 1)
     * @param string $name Status name (default: "Open")
     * @return TicketStatus Status object
     */
    private function createMockStatus(int $id = 1, string $name = 'Open'): TicketStatus {
        $status = new TicketStatus([
            'id' => $id,
            'name' => $name
        ]);
        TicketStatus::$mockData[$id] = $status;
        return $status;
    }

    /**
     * Create mock ticket
     *
     * @param int $id Ticket ID
     * @param string $number Ticket number
     * @param string $subject Ticket subject
     * @param int $statusId Status ID
     * @param int|null $pid Parent ID (optional)
     * @return Ticket Ticket object
     */
    private function createMockTicket(
        int $id,
        string $number,
        string $subject,
        int $statusId,
        ?int $pid = null
    ): Ticket {
        $ticket = new Ticket([
            'id' => $id,
            'number' => $number,
            'subject' => $subject,
            'status_id' => $statusId,
            'pid' => $pid,
        ]);
        Ticket::$mockData[$id] = $ticket;
        return $ticket;
    }

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
     * Register active Subticket plugin with createLink method
     *
     * @param array|null $callLog Optional reference to array for tracking method calls
     * @return object Mock plugin instance
     */
    private function registerSubticketPluginWithCreateLink(?array &$callLog = null): object {
        $mockPlugin = new class($callLog) {
            private $callLog;

            public function __construct(?array &$callLog = null) {
                $this->callLog = &$callLog;
            }

            public function isActive() {
                return true;
            }

            public function getName() {
                return 'Subticket Plugin';
            }

            // Mock method for hasParent
            public function hasParent($childTicket) {
                return $childTicket->getPid() !== null;
            }

            // Mock method for getParent
            public function getParent($childTicket) {
                $parentId = $childTicket->getPid();
                if (!$parentId) {
                    return null;
                }
                return Ticket::lookup($parentId);
            }

            // Mock method for createLink (NEW)
            public function createLink($parentTicket, $childTicket) {
                // Track method call if callLog provided
                if ($this->callLog !== null) {
                    $this->callLog[] = [
                        'parent_id' => $parentTicket->getId(),
                        'child_id' => $childTicket->getId(),
                    ];
                }

                // Simulate link creation by setting pid (use setter method)
                $childTicket->setPid($parentTicket->getId());
            }
        };

        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);

        return $mockPlugin;
    }

    /**
     * Register active Subticket plugin with hasParent check
     *
     * @param bool $hasParent Whether child has parent
     * @param int|null $existingParentId Existing parent ID
     * @return object Mock plugin instance
     */
    private function registerSubticketPluginWithHasParent(bool $hasParent, ?int $existingParentId = null): object {
        $mockPlugin = new class($hasParent, $existingParentId) {
            private $hasParent;
            private $existingParentId;

            public function __construct(bool $hasParent, ?int $existingParentId = null) {
                $this->hasParent = $hasParent;
                $this->existingParentId = $existingParentId;
            }

            public function isActive() {
                return true;
            }

            public function getName() {
                return 'Subticket Plugin';
            }

            // Mock method for hasParent
            public function hasParent($childTicket) {
                return $this->hasParent;
            }

            // Mock method for getParent
            public function getParent($childTicket) {
                if (!$this->hasParent || !$this->existingParentId) {
                    return null;
                }
                return Ticket::lookup($this->existingParentId);
            }

            // Mock method for createLink
            public function createLink($parentTicket, $childTicket) {
                // Should not be called if hasParent returns true
                throw new Exception('Cannot create link - child already has parent');
            }
        };

        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);

        return $mockPlugin;
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
