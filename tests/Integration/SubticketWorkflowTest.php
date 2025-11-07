<?php
/**
 * Integration Tests: Subticket Workflow Testing
 *
 * Tests complete user workflows across multiple endpoints:
 * - Create links → Verify relationships → Unlink → Verify cleanup
 * - Multi-child scenarios with partial unlink operations
 * - Cross-endpoint data consistency validation
 *
 * Test Environment:
 * - Uses REAL mock objects (not HTTP calls)
 * - Shared state across method calls within test
 * - Validates actual plugin method invocations
 *
 * @group integration
 */
class SubticketWorkflowTest extends PHPUnit\Framework\TestCase {

    /**
     * @var SubticketApiController
     */
    private $controller;

    /**
     * @var API
     */
    private $apiKey;

    /**
     * @var object Subticket plugin mock
     */
    private $plugin;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();

        // Reset test data factory counters
        SubticketTestDataFactory::reset();

        // Reset all mock data
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        TicketStatus::$mockData = [];

        // Create API key with permission
        $this->apiKey = SubticketTestDataFactory::createApiKeyWithPermission();

        // Create active plugin
        $this->plugin = SubticketTestDataFactory::createActivePlugin();
        PluginManager::getInstance()->registerPlugin('subticket', $this->plugin);

        // Create controller instance
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

        // Reset plugin registry
        PluginManager::getInstance()->registerPlugin('subticket', null);
    }

    /**
     * Test: Complete happy path workflow
     *
     * Scenario:
     * 1. Create parent ticket
     * 2. Create 2 child tickets
     * 3. Link both children to parent
     * 4. Verify getList() returns both children
     * 5. Unlink one child
     * 6. Verify getList() returns only remaining child
     *
     * @test
     */
    public function testCompleteWorkflowCreateLinkUnlink(): void {
        // 1. Create tickets
        $parent = SubticketTestDataFactory::createTicket(['subject' => 'Parent Ticket']);
        $child1 = SubticketTestDataFactory::createTicket(['subject' => 'Child 1']);
        $child2 = SubticketTestDataFactory::createTicket(['subject' => 'Child 2']);

        // 2. Link child1 to parent
        $result = $this->controller->createLink($parent->getId(), $child1->getId());

        $this->assertTrue($result['success']);
        $this->assertEquals('Subticket relationship created successfully', $result['message']);
        $this->assertEquals($parent->getId(), $result['parent']['ticket_id']);
        $this->assertEquals($child1->getId(), $result['child']['ticket_id']);

        // 3. Link child2 to parent
        $result = $this->controller->createLink($parent->getId(), $child2->getId());

        $this->assertTrue($result['success']);
        $this->assertEquals($child2->getId(), $result['child']['ticket_id']);

        // 4. Verify getList() returns both children
        $listResult = $this->controller->getList($parent->getId());

        $this->assertArrayHasKey('children', $listResult);
        $this->assertCount(2, $listResult['children']);

        $childIds = array_column($listResult['children'], 'ticket_id');
        $this->assertContains($child1->getId(), $childIds);
        $this->assertContains($child2->getId(), $childIds);

        // 5. Unlink child1
        $unlinkResult = $this->controller->unlinkChild($child1->getId());

        $this->assertTrue($unlinkResult['success']);
        $this->assertEquals('Subticket relationship removed successfully', $unlinkResult['message']);
        $this->assertEquals($child1->getId(), $unlinkResult['child']['ticket_id']);

        // 6. Verify getList() returns only child2
        $listResult = $this->controller->getList($parent->getId());

        $this->assertCount(1, $listResult['children']);
        $this->assertEquals($child2->getId(), $listResult['children'][0]['ticket_id']);

        // 7. Verify child1 has no parent
        $parentResult = $this->controller->getParent($child1->getId());

        $this->assertNull($parentResult['parent']);
    }

    /**
     * Test: Cross-endpoint consistency after linking
     *
     * Scenario:
     * 1. Create link between parent and child
     * 2. Call getParent(child) → Should return parent
     * 3. Call getList(parent) → Should include child
     * 4. Verify both endpoints return consistent data
     *
     * @test
     */
    public function testCrossEndpointConsistencyAfterLinking(): void {
        // 1. Create and link tickets
        $data = SubticketTestDataFactory::createLinkedPair($this->plugin);
        $parent = $data['parent'];
        $child = $data['child'];

        // 2. Get parent via getParent()
        $parentResult = $this->controller->getParent($child->getId());

        $this->assertNotNull($parentResult['parent']);
        $this->assertEquals($parent->getId(), $parentResult['parent']['ticket_id']);
        $this->assertEquals($parent->getNumber(), $parentResult['parent']['number']);
        $this->assertEquals($parent->getSubject(), $parentResult['parent']['subject']);

        // 3. Get children via getList()
        $listResult = $this->controller->getList($parent->getId());

        $this->assertCount(1, $listResult['children']);
        $this->assertEquals($child->getId(), $listResult['children'][0]['ticket_id']);

        // 4. Verify data format consistency
        // Both endpoints should return ticket data in same format
        $childFromList = $listResult['children'][0];

        $this->assertArrayHasKey('ticket_id', $childFromList);
        $this->assertArrayHasKey('number', $childFromList);
        $this->assertArrayHasKey('subject', $childFromList);
        $this->assertArrayHasKey('status', $childFromList);

        // Parent data should have same keys
        $parentFromGetParent = $parentResult['parent'];

        $this->assertArrayHasKey('ticket_id', $parentFromGetParent);
        $this->assertArrayHasKey('number', $parentFromGetParent);
        $this->assertArrayHasKey('subject', $parentFromGetParent);
        $this->assertArrayHasKey('status', $parentFromGetParent);
    }

    /**
     * Test: Multiple children with partial unlink
     *
     * Scenario:
     * 1. Create parent with 3 children
     * 2. Unlink middle child (child 2)
     * 3. Verify getList() returns only children 1 and 3
     * 4. Verify child 2 has no parent
     *
     * @test
     */
    public function testMultipleChildrenPartialUnlink(): void {
        // 1. Create parent with 3 children
        $data = SubticketTestDataFactory::createParentWithChildren(3, $this->plugin);
        $parent = $data['parent'];
        $children = $data['children'];

        // 2. Verify all 3 children are linked
        $listResult = $this->controller->getList($parent->getId());
        $this->assertCount(3, $listResult['children']);

        // 3. Unlink middle child (child 2)
        $middleChild = $children[1]; // Index 1 = second child
        $unlinkResult = $this->controller->unlinkChild($middleChild->getId());

        $this->assertTrue($unlinkResult['success']);

        // 4. Verify getList() returns only 2 children
        $listResult = $this->controller->getList($parent->getId());
        $this->assertCount(2, $listResult['children']);

        $remainingChildIds = array_column($listResult['children'], 'ticket_id');
        $this->assertContains($children[0]->getId(), $remainingChildIds);
        $this->assertContains($children[2]->getId(), $remainingChildIds);
        $this->assertNotContains($middleChild->getId(), $remainingChildIds);

        // 5. Verify middle child has no parent
        $parentResult = $this->controller->getParent($middleChild->getId());
        $this->assertNull($parentResult['parent']);

        // 6. Verify other children still have parent
        $parentResult1 = $this->controller->getParent($children[0]->getId());
        $this->assertEquals($parent->getId(), $parentResult1['parent']['ticket_id']);

        $parentResult3 = $this->controller->getParent($children[2]->getId());
        $this->assertEquals($parent->getId(), $parentResult3['parent']['ticket_id']);
    }

    /**
     * Test: Data format consistency across endpoints
     *
     * Scenario:
     * 1. Create linked pair
     * 2. Get parent data via getParent()
     * 3. Get parent data via getList() (same parent should appear in child's parent field)
     * 4. Verify formatTicketData() returns identical structure
     *
     * @test
     */
    public function testDataFormatConsistencyAcrossEndpoints(): void {
        // 1. Create linked pair
        $data = SubticketTestDataFactory::createLinkedPair($this->plugin);
        $parent = $data['parent'];
        $child = $data['child'];

        // 2. Get parent via getParent()
        $parentViaGetParent = $this->controller->getParent($child->getId());
        $parentData1 = $parentViaGetParent['parent'];

        // 3. Get child via getList()
        $childViaGetList = $this->controller->getList($parent->getId());
        $childData = $childViaGetList['children'][0];

        // 4. Both should have identical structure
        $this->assertSame(
            array_keys($parentData1),
            array_keys($childData),
            'formatTicketData() should return identical keys for all tickets'
        );

        // 5. Verify structure has required fields
        $requiredFields = ['ticket_id', 'number', 'subject', 'status'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $parentData1, "Parent data must have field: $field");
            $this->assertArrayHasKey($field, $childData, "Child data must have field: $field");
        }

        // 6. Verify ticket_id is integer (not string)
        $this->assertIsInt($parentData1['ticket_id']);
        $this->assertIsInt($childData['ticket_id']);

        // 7. Verify status is string
        $this->assertIsString($parentData1['status']);
        $this->assertIsString($childData['status']);
    }

    /**
     * Test: Relink child to different parent
     *
     * Scenario:
     * 1. Link child to parent1
     * 2. Unlink child
     * 3. Link same child to parent2
     * 4. Verify new relationship is correct
     *
     * @test
     */
    public function testRelinkChildToDifferentParent(): void {
        // 1. Create tickets
        $parent1 = SubticketTestDataFactory::createTicket(['subject' => 'Parent 1']);
        $parent2 = SubticketTestDataFactory::createTicket(['subject' => 'Parent 2']);
        $child = SubticketTestDataFactory::createTicket(['subject' => 'Child']);

        // 2. Link child to parent1
        $this->controller->createLink($parent1->getId(), $child->getId());

        // Verify link
        $parentResult = $this->controller->getParent($child->getId());
        $this->assertEquals($parent1->getId(), $parentResult['parent']['ticket_id']);

        // 3. Unlink child
        $this->controller->unlinkChild($child->getId());

        // Verify unlinked
        $parentResult = $this->controller->getParent($child->getId());
        $this->assertNull($parentResult['parent']);

        // 4. Link child to parent2
        $this->controller->createLink($parent2->getId(), $child->getId());

        // Verify new link
        $parentResult = $this->controller->getParent($child->getId());
        $this->assertEquals($parent2->getId(), $parentResult['parent']['ticket_id']);

        // Verify parent1 has no children
        $listResult1 = $this->controller->getList($parent1->getId());
        $this->assertEmpty($listResult1['children']);

        // Verify parent2 has child
        $listResult2 = $this->controller->getList($parent2->getId());
        $this->assertCount(1, $listResult2['children']);
        $this->assertEquals($child->getId(), $listResult2['children'][0]['ticket_id']);
    }

    /**
     * Test: Large number of children
     *
     * Scenario:
     * 1. Create parent with 10 children
     * 2. Verify getList() returns all 10
     * 3. Verify each child's getParent() returns correct parent
     *
     * @test
     */
    public function testLargeNumberOfChildren(): void {
        // 1. Create parent with 10 children
        $data = SubticketTestDataFactory::createParentWithChildren(10, $this->plugin);
        $parent = $data['parent'];
        $children = $data['children'];

        // 2. Verify getList() returns all 10
        $listResult = $this->controller->getList($parent->getId());

        $this->assertCount(10, $listResult['children']);

        // 3. Verify all children IDs are present
        $returnedChildIds = array_column($listResult['children'], 'ticket_id');
        foreach ($children as $child) {
            $this->assertContains(
                $child->getId(),
                $returnedChildIds,
                sprintf('Child %d should be in parent\'s list', $child->getId())
            );
        }

        // 4. Verify each child's getParent() returns correct parent
        foreach ($children as $child) {
            $parentResult = $this->controller->getParent($child->getId());
            $this->assertEquals(
                $parent->getId(),
                $parentResult['parent']['ticket_id'],
                sprintf('Child %d should have parent %d', $child->getId(), $parent->getId())
            );
        }
    }

    /**
     * Test: Complete cleanup after unlink
     *
     * Scenario:
     * 1. Create link
     * 2. Unlink
     * 3. Verify getParent() returns null
     * 4. Verify child not in parent's list
     *
     * @test
     */
    public function testCompleteCleanupAfterUnlink(): void {
        // 1. Create and link tickets
        $data = SubticketTestDataFactory::createLinkedPair($this->plugin);
        $parent = $data['parent'];
        $child = $data['child'];

        // 2. Verify link exists
        $parentResult = $this->controller->getParent($child->getId());
        $this->assertNotNull($parentResult['parent']);

        // 3. Unlink child
        $this->controller->unlinkChild($child->getId());

        // 4. Verify getParent() returns null
        $parentResult = $this->controller->getParent($child->getId());
        $this->assertNull($parentResult['parent'], 'getParent() should return null after unlink');

        // 5. Verify child not in parent's list
        $listResult = $this->controller->getList($parent->getId());
        $this->assertEmpty(
            $listResult['children'],
            'Parent should have no children after unlink'
        );
    }

    /**
     * Test: Unlink all children sequentially
     *
     * Scenario:
     * 1. Create parent with 3 children
     * 2. Unlink all 3 sequentially
     * 3. Verify getList() returns empty array
     *
     * @test
     */
    public function testUnlinkAllChildrenSequentially(): void {
        // 1. Create parent with 3 children
        $data = SubticketTestDataFactory::createParentWithChildren(3, $this->plugin);
        $parent = $data['parent'];
        $children = $data['children'];

        // 2. Verify all 3 are linked
        $listResult = $this->controller->getList($parent->getId());
        $this->assertCount(3, $listResult['children']);

        // 3. Unlink all children sequentially
        foreach ($children as $child) {
            $this->controller->unlinkChild($child->getId());
        }

        // 4. Verify getList() returns empty array
        $listResult = $this->controller->getList($parent->getId());
        $this->assertEmpty($listResult['children'], 'All children should be unlinked');

        // 5. Verify each child has no parent
        foreach ($children as $child) {
            $parentResult = $this->controller->getParent($child->getId());
            $this->assertNull(
                $parentResult['parent'],
                sprintf('Child %d should have no parent', $child->getId())
            );
        }
    }
}
