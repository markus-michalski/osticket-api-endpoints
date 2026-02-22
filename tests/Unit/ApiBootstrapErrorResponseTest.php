<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/ApiBootstrap.php';

#[CoversClass(ApiBootstrap::class)]
class ApiBootstrapErrorResponseTest extends TestCase
{
    protected function setUp(): void
    {
        Http::reset();
        Http::$throwOnResponse = true;
    }

    protected function tearDown(): void
    {
        Http::reset();
    }

    #[Test]
    public function sendErrorResponseReturnsJsonContentType(): void
    {
        try {
            ApiBootstrap::sendErrorResponse(400, 'Bad Request');
        } catch (RuntimeException) {
            // Expected — Http mock throws instead of exit()
        }

        $this->assertNotNull(Http::$lastResponse);
        $this->assertSame('application/json', Http::$lastResponse['contentType']);
    }

    #[Test]
    public function sendErrorResponseReturnsValidJson(): void
    {
        try {
            ApiBootstrap::sendErrorResponse(400, 'Invalid parameters');
        } catch (RuntimeException) {
        }

        $body = Http::$lastResponse['content'];
        $decoded = json_decode($body, true);

        $this->assertNotNull($decoded, 'Response body must be valid JSON');
        $this->assertArrayHasKey('error', $decoded);
        $this->assertArrayHasKey('message', $decoded);
    }

    #[Test]
    public function sendErrorResponseContainsErrorFlag(): void
    {
        try {
            ApiBootstrap::sendErrorResponse(422, 'Validation failed');
        } catch (RuntimeException) {
        }

        $decoded = json_decode(Http::$lastResponse['content'], true);
        $this->assertTrue($decoded['error']);
    }

    #[Test]
    public function sendErrorResponseContainsExactMessage(): void
    {
        $message = 'Ticket not found: 12345';

        try {
            ApiBootstrap::sendErrorResponse(404, $message);
        } catch (RuntimeException) {
        }

        $decoded = json_decode(Http::$lastResponse['content'], true);
        $this->assertSame($message, $decoded['message']);
    }

    #[Test]
    public function sendErrorResponsePreservesHttpStatusCode(): void
    {
        try {
            ApiBootstrap::sendErrorResponse(500, 'Internal error');
        } catch (RuntimeException) {
        }

        $this->assertSame(500, Http::$lastResponse['code']);
    }

    #[Test]
    public function sendErrorResponsePreservesUnicodeCharacters(): void
    {
        $message = 'Ungültige Parameter: Ä Ö Ü ß';

        try {
            ApiBootstrap::sendErrorResponse(400, $message);
        } catch (RuntimeException) {
        }

        $decoded = json_decode(Http::$lastResponse['content'], true);
        $this->assertSame($message, $decoded['message']);
    }

    #[Test]
    public function handleExceptionSendsJsonResponse(): void
    {
        $bootstrap = new ApiBootstrap();
        $exception = new Exception('Failed to post note', 400);

        try {
            $bootstrap->handleException($exception);
        } catch (RuntimeException) {
        }

        $this->assertNotNull(Http::$lastResponse);
        $this->assertSame(400, Http::$lastResponse['code']);
        $this->assertSame('application/json', Http::$lastResponse['contentType']);

        $decoded = json_decode(Http::$lastResponse['content'], true);
        $this->assertSame('Failed to post note', $decoded['message']);
    }

    #[Test]
    public function handleExceptionNormalizesInvalidStatusCode(): void
    {
        $bootstrap = new ApiBootstrap();
        $exception = new Exception('Something broke', 0);

        try {
            $bootstrap->handleException($exception);
        } catch (RuntimeException) {
        }

        $this->assertSame(400, Http::$lastResponse['code']);
    }
}
