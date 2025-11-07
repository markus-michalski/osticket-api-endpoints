<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: Subticket Permission Tests
 *
 * Tests für can_manage_subtickets Permission-Check
 * Tests für Subticket-Plugin-Erkennung
 *
 * EXPECTATION: ALL TESTS MUST FAIL (RED PHASE)
 * Implementation kommt in GREEN Phase
 */
class SubticketPermissionTest extends TestCase {

    protected $controller;

    protected function setUp(): void {
        parent::setUp();

        // Reset mock data
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];

        // Reset PluginManager
        $pluginManager = PluginManager::getInstance();
    }

    protected function tearDown(): void {
        parent::tearDown();

        // Clean up
        API::$mockData = [];
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
    }

    /**
     * Test: Permission-Check funktioniert wenn can_manage_subtickets = true
     *
     * Expected: Method hasSubticketPermission() returns true
     * Implementation: SubticketApiController->hasSubticketPermission()
     */
    public function testRequiresSubticketPermission(): void {
        // Arrange: Create API key WITH subticket permission
        $apiKey = new API([
            'key' => 'test-key-with-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => true, // NEW PERMISSION
            ]
        ]);

        API::$mockData['test-key-with-permission'] = $apiKey;

        // Act: Check permission
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey); // Set test API key
        $hasPermission = $controller->hasSubticketPermission();

        // Assert: Permission should be granted
        $this->assertTrue($hasPermission,
            'API key with can_manage_subtickets should have permission');
    }

    /**
     * Test: Throws Exception wenn Permission fehlt
     *
     * Expected: Method requireSubticketPermission() throws Exception with code 403
     * Implementation: SubticketApiController->requireSubticketPermission()
     */
    public function testThrowsExceptionWhenPermissionMissing(): void {
        // Arrange: Create API key WITHOUT subticket permission
        $apiKey = new API([
            'key' => 'test-key-no-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => false, // NO PERMISSION
            ]
        ]);

        API::$mockData['test-key-no-permission'] = $apiKey;

        // Expect Exception
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('API key not authorized for subticket operations');

        // Act: Try to require permission
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey); // Set test API key
        $controller->requireSubticketPermission();
    }

    /**
     * Test: SubticketPlugin Detection funktioniert
     *
     * Expected: Method isSubticketPluginAvailable() returns true when plugin exists
     * Implementation: SubticketApiController->isSubticketPluginAvailable()
     */
    public function testDetectsSubticketPluginAvailability(): void {
        // Arrange: Register SubticketPlugin in PluginManager
        $mockPlugin = new class {
            public function isActive() { return true; }
            public function getName() { return 'Subticket Plugin'; }
        };

        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);

        // Act: Check plugin availability
        $controller = $this->createSubticketController();
        $isAvailable = $controller->isSubticketPluginAvailable();

        // Assert: Plugin should be detected
        $this->assertTrue($isAvailable,
            'Subticket plugin should be detected when registered');
    }

    /**
     * Test: Gibt false zurück wenn Plugin nicht verfügbar
     *
     * Expected: Method isSubticketPluginAvailable() returns false
     * Implementation: SubticketApiController->isSubticketPluginAvailable()
     */
    public function testReturnsFalseWhenPluginNotAvailable(): void {
        // Arrange: NO plugin registered (clean state)
        PluginManager::getInstance()->registerPlugin('subticket', null);

        // Act: Check plugin availability
        $controller = $this->createSubticketController();
        $isAvailable = $controller->isSubticketPluginAvailable();

        // Assert: Plugin should NOT be detected
        $this->assertFalse($isAvailable,
            'Subticket plugin should not be detected when not registered');
    }

    /**
     * Test: Gibt false zurück wenn Plugin existiert aber inaktiv ist
     *
     * Expected: Method isSubticketPluginAvailable() returns false
     * Implementation: SubticketApiController->isSubticketPluginAvailable()
     */
    public function testReturnsFalseWhenPluginExistsButInactive(): void {
        // Arrange: Register inactive plugin
        $mockPlugin = new class {
            public function isActive() { return false; } // INACTIVE
            public function getName() { return 'Subticket Plugin'; }
        };

        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);

        // Act: Check plugin availability
        $controller = $this->createSubticketController();
        $isAvailable = $controller->isSubticketPluginAvailable();

        // Assert: Plugin should NOT be available when inactive
        $this->assertFalse($isAvailable,
            'Subticket plugin should not be available when inactive');
    }

    /**
     * Test: Kombinierter Check - Permission UND Plugin verfügbar
     *
     * Expected: Method canUseSubtickets() returns true when BOTH conditions met
     * Implementation: SubticketApiController->canUseSubtickets()
     */
    public function testCanUseSubticketsWhenBothConditionsMet(): void {
        // Arrange: API key WITH permission
        $apiKey = new API([
            'key' => 'test-key-full-access',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => true,
            ]
        ]);
        API::$mockData['test-key-full-access'] = $apiKey;

        // Arrange: Plugin available
        $mockPlugin = new class {
            public function isActive() { return true; }
            public function getName() { return 'Subticket Plugin'; }
        };
        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);

        // Act: Combined check
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey); // Set test API key
        $canUse = $controller->canUseSubtickets();

        // Assert: Should return true
        $this->assertTrue($canUse,
            'Should allow subticket operations when both permission and plugin are available');
    }

    /**
     * Test: Kombinierter Check - Permission fehlt
     *
     * Expected: Method canUseSubtickets() returns false when permission missing
     * Implementation: SubticketApiController->canUseSubtickets()
     */
    public function testCannotUseSubticketsWhenPermissionMissing(): void {
        // Arrange: API key WITHOUT permission
        $apiKey = new API([
            'key' => 'test-key-no-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => false, // NO PERMISSION
            ]
        ]);
        API::$mockData['test-key-no-permission'] = $apiKey;

        // Arrange: Plugin available
        $mockPlugin = new class {
            public function isActive() { return true; }
        };
        PluginManager::getInstance()->registerPlugin('subticket', $mockPlugin);

        // Act: Combined check
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey); // Set test API key
        $canUse = $controller->canUseSubtickets();

        // Assert: Should return false
        $this->assertFalse($canUse,
            'Should deny subticket operations when permission is missing');
    }

    /**
     * Test: Kombinierter Check - Plugin fehlt
     *
     * Expected: Method canUseSubtickets() returns false when plugin not available
     * Implementation: SubticketApiController->canUseSubtickets()
     */
    public function testCannotUseSubticketsWhenPluginNotAvailable(): void {
        // Arrange: API key WITH permission
        $apiKey = new API([
            'key' => 'test-key-with-permission',
            'permissions' => [
                'can_create_tickets' => true,
                'can_manage_subtickets' => true,
            ]
        ]);
        API::$mockData['test-key-with-permission'] = $apiKey;

        // Arrange: NO plugin available
        PluginManager::getInstance()->registerPlugin('subticket', null);

        // Act: Combined check
        $controller = $this->createSubticketController();
        $controller->setTestApiKey($apiKey); // Set test API key
        $canUse = $controller->canUseSubtickets();

        // Assert: Should return false
        $this->assertFalse($canUse,
            'Should deny subticket operations when plugin is not available');
    }

    /**
     * Test: API-Key Mock hat can_manage_subtickets Property
     *
     * Expected: API mock should have can_manage_subtickets in ht array
     * Implementation: API class mock in tests/bootstrap.php
     */
    public function testApiKeyMockHasSubticketPermissionProperty(): void {
        // Arrange: Create API key with subticket permission
        $apiKey = new API([
            'key' => 'test-key',
            'permissions' => [
                'can_manage_subtickets' => true,
            ]
        ]);

        // Assert: ht array should contain can_manage_subtickets
        $this->assertArrayHasKey('can_manage_subtickets', $apiKey->ht,
            'API mock should have can_manage_subtickets in ht array');

        $this->assertTrue($apiKey->ht['can_manage_subtickets'],
            'can_manage_subtickets should be true when set in permissions');
    }

    /**
     * Helper: Create SubticketApiController instance
     *
     * @return object SubticketApiController instance
     */
    private function createSubticketController() {
        // This will fail in RED phase because file doesn't exist yet
        require_once __DIR__ . '/../../controllers/SubticketApiController.php';
        return new SubticketApiController();
    }
}
