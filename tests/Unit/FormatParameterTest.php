<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: Format Parameter Tests
 *
 * Tests für format-Parameter Validierung (markdown/html/text)
 */
class FormatParameterTest extends TestCase {

    public function testAcceptsMarkdownFormat(): void {
        $controller = $this->createController();

        $result = $controller->validateFormat('markdown');

        $this->assertEquals('markdown', $result);
    }

    public function testAcceptsHtmlFormat(): void {
        $controller = $this->createController();

        $result = $controller->validateFormat('html');

        $this->assertEquals('html', $result);
    }

    public function testAcceptsTextFormat(): void {
        $controller = $this->createController();

        $result = $controller->validateFormat('text');

        $this->assertEquals('text', $result);
    }

    public function testRejectsInvalidFormat(): void {
        $controller = $this->createController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $controller->validateFormat('invalid');
    }

    public function testNormalizesFormatToLowercase(): void {
        $controller = $this->createController();

        $result = $controller->validateFormat('MARKDOWN');

        $this->assertEquals('markdown', $result);
    }

    public function testTrimsWhitespaceFromFormat(): void {
        $controller = $this->createController();

        $result = $controller->validateFormat('  html  ');

        $this->assertEquals('html', $result);
    }

    public function testRejectsEmptyFormat(): void {
        $controller = $this->createController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $controller->validateFormat('');
    }

    public function testRejectsNullFormat(): void {
        $controller = $this->createController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $controller->validateFormat(null);
    }

    private function createController() {
        // Controller existiert noch nicht - RED Phase!
        require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';
        return new ExtendedTicketApiController();
    }
}
