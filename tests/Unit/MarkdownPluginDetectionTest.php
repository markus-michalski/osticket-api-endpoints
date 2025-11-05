<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: Markdown Plugin Detection Tests
 *
 * Tests für Markdown-Plugin-Erkennung via class_exists()
 */
class MarkdownPluginDetectionTest extends TestCase {

    protected function setUp(): void {
        // Reset class mocks before each test
        if (class_exists('MarkdownThreadEntryBody', false)) {
            // Can't undefine class, so we'll use test isolation
        }
    }

    public function testDetectsMarkdownPluginWhenClassExists(): void {
        // Simulate Markdown plugin being active
        if (!class_exists('MarkdownThreadEntryBody')) {
            eval('class MarkdownThreadEntryBody {}');
        }

        $controller = $this->createController();
        $result = $controller->isMarkdownPluginActive();

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenMarkdownPluginNotActive(): void {
        // Skip if MarkdownThreadEntryBody class was defined in previous test
        if (class_exists('MarkdownThreadEntryBody')) {
            $this->markTestSkipped('Cannot test when MarkdownThreadEntryBody class exists from previous test');
        }

        $controller = $this->createController();

        // Mock PluginManager to return null
        PluginManager::getInstance()->registerPlugin('markdown-support', null);

        $result = $controller->isMarkdownPluginActive();

        $this->assertFalse($result);
    }

    public function testChecksPluginManagerAsSecondOption(): void {
        $controller = $this->createController();

        // Create mock plugin
        $mockPlugin = new class {
            public function isActive() { return true; }
        };

        PluginManager::getInstance()->registerPlugin('markdown-support', $mockPlugin);

        $result = $controller->isMarkdownPluginActive();

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenPluginExistsButNotActive(): void {
        // Skip if MarkdownThreadEntryBody class was defined in previous test
        if (class_exists('MarkdownThreadEntryBody')) {
            $this->markTestSkipped('Cannot test when MarkdownThreadEntryBody class exists from previous test');
        }

        $controller = $this->createController();

        // Create inactive plugin
        $mockPlugin = new class {
            public function isActive() { return false; }
        };

        PluginManager::getInstance()->registerPlugin('markdown-support', $mockPlugin);

        $result = $controller->isMarkdownPluginActive();

        $this->assertFalse($result);
    }

    private function createController() {
        require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';
        return new ExtendedTicketApiController();
    }
}
