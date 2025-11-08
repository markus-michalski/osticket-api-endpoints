<?php
/**
 * Integration Tests: Plugin Unavailability Scenarios
 *
 * Tests all 4 endpoints behavior when subticket plugin is:
 * - Not installed
 * - Installed but inactive
 * - Installed but returns null
 *
 * Expected: ALL endpoints return 501 Not Implemented
 *
 * @group integration
 */
class SubticketPluginUnavailableTest extends PHPUnit\Framework\TestCase {

    /**
     * @var SubticketApiController
     */
    private $controller;

    /**
     * @var API
     */
    private $apiKey;

    /**
     * Set up test environment with NO plugin available
     */
    protected function setUp(): void {
        parent::setUp();

        // Reset test data
        SubticketTestDataFactory::reset();
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        TicketStatus::$mockData = [];

        // Create API key WITH permission (permission OK, plugin NOT OK)
        $this->apiKey = SubticketTestDataFactory::createApiKeyWithPermission();

        // Disable plugin mock (simulate plugin not available)
        SubticketPlugin::$mockEnabled = false;

        // Create controller
        $this->controller = new SubticketApiController();
        $this->controller->setTestApiKey($this->apiKey);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void {
        parent::tearDown();

        // Reset ALL mock data
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        TicketStatus::$mockData = [];

        // Re-enable plugin mock for other tests
        SubticketPlugin::$mockEnabled = true;
    }

    /**
     * Test: getParent() when plugin not installed → 501 error
     *
     * Scenario:
     * - API key has permission
     * - Subticket plugin is NOT installed (null)
     * - Call getParent(childId)
     *
     * Expected:
     * - Exception with code 501
     * - Message: "Subticket plugin not available"
     *
     * @test
     */
    public function testGetParentWithoutPlugin(): void {
        // Create child ticket
        $child = SubticketTestDataFactory::createTicket(['subject' => 'Child Ticket']);

        // Expect 501 error
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Try to get parent (should fail)
        $this->controller->getParent($child->getId());
    }

    /**
     * Test: getList() when plugin not installed → 501 error
     *
     * Scenario:
     * - API key has permission
     * - Subticket plugin is NOT installed (null)
     * - Call getList(parentId)
     *
     * Expected:
     * - Exception with code 501
     * - Message: "Subticket plugin not available"
     *
     * @test
     */
    public function testGetListWithoutPlugin(): void {
        // Create parent ticket
        $parent = SubticketTestDataFactory::createTicket(['subject' => 'Parent Ticket']);

        // Expect 501 error
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Try to get list (should fail)
        $this->controller->getList($parent->getId());
    }

    /**
     * Test: createLink() when plugin not installed → 501 error
     *
     * Scenario:
     * - API key has permission
     * - Subticket plugin is NOT installed (null)
     * - Call createLink(parentId, childId)
     *
     * Expected:
     * - Exception with code 501
     * - Message: "Subticket plugin not available"
     *
     * @test
     */
    public function testCreateLinkWithoutPlugin(): void {
        // Create tickets
        $parent = SubticketTestDataFactory::createTicket(['subject' => 'Parent Ticket']);
        $child = SubticketTestDataFactory::createTicket(['subject' => 'Child Ticket']);

        // Expect 501 error
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Try to create link (should fail)
        $this->controller->createLink($parent->getId(), $child->getId());
    }

    /**
     * Test: unlinkChild() when plugin not installed → 501 error
     *
     * Scenario:
     * - API key has permission
     * - Subticket plugin is NOT installed (null)
     * - Call unlinkChild(childId)
     *
     * Expected:
     * - Exception with code 501
     * - Message: "Subticket plugin not available"
     *
     * @test
     */
    public function testUnlinkChildWithoutPlugin(): void {
        // Create child ticket
        $child = SubticketTestDataFactory::createTicket(['subject' => 'Child Ticket']);

        // Expect 501 error
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Try to unlink (should fail)
        $this->controller->unlinkChild($child->getId());
    }

    /**
     * Test: Verify plugin availability check works correctly
     *
     * Scenario:
     * - Register inactive plugin (isActive() returns false)
     * - Call any endpoint
     *
     * Expected:
     * - Same 501 error as with null plugin
     *
     * @test
     */
    public function testInactivePluginTreatedAsUnavailable(): void {
        // Register inactive plugin
        $inactivePlugin = SubticketTestDataFactory::createInactivePlugin();
        PluginManager::getInstance()->registerPlugin('subticket', $inactivePlugin);

        // Create ticket
        $child = SubticketTestDataFactory::createTicket(['subject' => 'Child Ticket']);

        // Expect 501 error (same as null plugin)
        $this->expectException(Exception::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessage('Subticket plugin not available');

        // Try to get parent (should fail)
        $this->controller->getParent($child->getId());
    }
}
