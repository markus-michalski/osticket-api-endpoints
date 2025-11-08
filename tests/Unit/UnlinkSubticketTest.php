<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: DELETE Unlink Subticket Endpoint Tests
 *
 * Tests für DELETE /api/tickets-subtickets-unlink.php
 *
 * EXPECTATION: ALL TESTS MUST FAIL (RED PHASE)
 * Implementation kommt in GREEN Phase
 *
 * API-Spec:
 * - DELETE /api/tickets-subtickets-unlink.php (or POST with _method=DELETE)
 * - Body: {"child_id": 200}
 * - Removes parent-child relationship between tickets
 * - Requires can_manage_subtickets permission
 * - Requires Subticket plugin available
 *
 * Response Format (Success):
 * {
 *   "success": true,
 *   "message": "Subticket relationship removed successfully",
 *   "child": {
 *     "ticket_id": 200,
 *     "number": "ABC200",
 *     "subject": "Child Ticket",
 *     "status": "Open"
 *   }
 * }
 */
class UnlinkSubticketTest extends TestCase {

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
     * HAPPY PATH: Unlinks child from parent successfully
     *
     * Test Case:
     * - Child ticket (ID: 200) exists
     * - Child has parent (ID: 100)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Returns array with "success" => true
     * - Returns "message" with success text
     * - Returns "child" with ticket data
     * - Plugin->removeLink() is called
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testUnlinksChildFromParentSuccessfully(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create parent ticket
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: Create child ticket with parent
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 100);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available with removeLink() method
        $plugin = $this->registerSubticketPluginWithRemoveLink();

        // Act: Unlink child from parent
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->unlinkChild('ABC200');

        // Assert: Success response
        $this->assertIsArray($result,
            'Result should be an array');

        $this->assertArrayHasKey('success', $result,
            'Result should have "success" key');

        $this->assertTrue($result['success'],
            'Success should be true');

        $this->assertArrayHasKey('message', $result,
            'Result should have "message" key');

        $this->assertStringContainsString('removed', strtolower($result['message']),
            'Message should contain "removed"');

        $this->assertStringContainsString('successfully', strtolower($result['message']),
            'Message should contain "successfully"');
    }

    /**
     * HAPPY PATH: Returns child data after unlinking
     *
     * Test Case:
     * - Child unlinked successfully
     * - Child ticket exists
     *
     * Expected:
     * - Response contains "child" key with full ticket data
     * - Child has: ticket_id, number, subject, status
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testReturnsChildDataAfterUnlinking(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create parent ticket
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: Create child ticket with parent
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 100);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithRemoveLink();

        // Act: Unlink child
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->unlinkChild('ABC200');

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
     * HAPPY PATH: Calls plugin removeLink method
     *
     * Test Case:
     * - Verify that plugin->removeLink() is called with correct parameter
     *
     * Expected:
     * - Plugin->removeLink($childTicket) is invoked
     * - Unlink succeeds without exception
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testCallsPluginRemoveLink(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create parent ticket
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: Create child ticket with parent
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 100);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithRemoveLink();

        // Reset call log before test
        SubticketPlugin::resetCallLog();

        // Act: Unlink child
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $result = $controller->unlinkChild('ABC200');

        // Assert: Plugin was called
        $this->assertCount(1, SubticketPlugin::$callLog['unlinkTicket'] ?? [],
            'Plugin unlinkTicket should be called exactly once');

        $unlinkCalls = SubticketPlugin::$callLog['unlinkTicket'];
        $this->assertEquals(200, $unlinkCalls[0]['childId'],
            'Plugin should be called with child ID 200');
    }

    /**
     * ERROR CASE: Returns 404 when child not found
     *
     * Test Case:
     * - Child ticket ID does not exist
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 404
     * - Error message: "Child ticket not found"
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testReturns404WhenChildNotFound(): void {
        // Arrange: No child ticket (ID 999 does not exist)

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithRemoveLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Child ticket not found');

        // Act: Try to unlink non-existent child
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->unlinkChild('999'); // Child 999 does not exist
    }

    /**
     * ERROR CASE: Returns 403 when API key lacks permission
     *
     * Test Case:
     * - Child ticket exists with parent
     * - API key does NOT have can_manage_subtickets permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 403 (Forbidden)
     * - Error message: "API key not authorized for subticket operations"
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testReturns403WhenApiKeyLacksPermission(): void {
        // Arrange: Create tickets
        $status = $this->createMockStatus();
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 100);

        // Arrange: API key WITHOUT subticket permission
        $apiKey = new API([
            'key' => 'test-key-no-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => false, // NO PERMISSION
            ]
        ]);

        // Arrange: Plugin available
        $this->registerSubticketPluginWithRemoveLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('API key not authorized for subticket operations');

        // Act: Try to unlink without permission
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->unlinkChild('ABC200');
    }

    /**
     * ERROR CASE: Returns 501 when plugin not available
     *
     * Test Case:
     * - Child ticket exists with parent
     * - API key has permission
     * - Subticket plugin is NOT available/installed
     *
     * Expected:
     * - Throws Exception with code 501 (Not Implemented)
     * - Error message: "Subticket plugin not available"
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testReturns501WhenPluginNotAvailable(): void {
        // Arrange: Create tickets
        $status = $this->createMockStatus();
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 100);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: NO plugin available
        SubticketPlugin::$mockEnabled = false;

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Act: Try to unlink without plugin
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->unlinkChild('ABC200');
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
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testReturns400WhenChildIdInvalid(): void {
        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available
        $this->registerSubticketPluginWithRemoveLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid child ticket number');

        // Act: Try to unlink with invalid child ID
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->unlinkChild(0); // Invalid: ID must be > 0
    }

    /**
     * ERROR CASE: Returns 404 when child has no parent
     *
     * Test Case:
     * - Child ticket exists
     * - Child has NO parent (nothing to unlink)
     * - API key has permission
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 404 (Not Found)
     * - Error message: "Child has no parent to unlink"
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     */
    public function testReturns404WhenChildHasNoParent(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create child ticket WITHOUT parent (pid = null)
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, null);

        // Arrange: API key with permission
        $apiKey = $this->createApiKeyWithPermission();

        // Arrange: Plugin available (hasParent returns false)
        $plugin = $this->registerSubticketPluginWithHasParent(false);

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Child has no parent to unlink');

        // Act: Try to unlink child without parent
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->unlinkChild('ABC200');
    }

    /**
     * ERROR CASE: Returns 403 when department access denied
     *
     * Test Case:
     * - Child ticket exists with parent
     * - API key has subticket permission
     * - API key does NOT have access to child ticket's department
     * - Plugin is available
     *
     * Expected:
     * - Throws Exception with code 403 (Forbidden)
     * - Error message: "Access denied to child ticket department"
     *
     * Implementation Method: SubticketApiController->unlinkChild(int $childId): array
     *
     * NOTE: This test documents expected behavior when department restrictions are implemented.
     * Current implementation allows all departments (TODO in controller).
     * Test will pass once department restrictions are fully implemented.
     */
    public function testReturns403WhenDepartmentAccessDenied(): void {
        // Arrange: Create status
        $status = $this->createMockStatus();

        // Arrange: Create parent ticket
        $parentTicket = $this->createMockTicket(100, 'ABC100', 'Parent Ticket', 1);

        // Arrange: Create child ticket with parent
        $childTicket = $this->createMockTicket(200, 'ABC200', 'Child Ticket', 1, 100);

        // Arrange: API key with permission but restricted departments
        // NOTE: This will be implemented when department restrictions are added to API keys
        $apiKey = new API([
            'key' => 'test-key-dept-restricted',
            'permissions' => [
                'can_manage_subtickets' => true,
            ],
            'restricted_departments' => [999], // Not allowed to access child's department
        ]);

        // Arrange: Plugin available
        $this->registerSubticketPluginWithRemoveLink();

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Access denied to child ticket department');

        // Act: Try to unlink child from restricted department
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey);
        $controller->unlinkChild('ABC200');
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
     * Register active Subticket plugin with removeLink method
     *
     * @param array|null $callLog Optional reference to array for tracking method calls
     * @return object Mock plugin instance
     */
    private function registerSubticketPluginWithRemoveLink(?array &$callLog = null): object {
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

            // Mock method for removeLink
            public function removeLink($childTicket) {
                // Track method call if callLog provided
                if ($this->callLog !== null) {
                    $this->callLog[] = [
                        'child_id' => $childTicket->getId(),
                    ];
                }

                // Simulate link removal by setting pid to null
                $childTicket->setPid(null);
            }
        };

        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);

        return $mockPlugin;
    }

    /**
     * Register active Subticket plugin with hasParent check
     *
     * @param bool $hasParent Whether child has parent
     * @return object Mock plugin instance
     */
    private function registerSubticketPluginWithHasParent(bool $hasParent): object {
        $mockPlugin = new class($hasParent) {
            private $hasParent;

            public function __construct(bool $hasParent) {
                $this->hasParent = $hasParent;
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
                if (!$this->hasParent) {
                    return null;
                }
                $parentId = $childTicket->getPid();
                if (!$parentId) {
                    return null;
                }
                return Ticket::lookup($parentId);
            }

            // Mock method for removeLink
            public function removeLink($childTicket) {
                if (!$this->hasParent) {
                    throw new Exception('Cannot remove link - child has no parent');
                }
                $childTicket->setPid(null);
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
