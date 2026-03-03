<?php

use PHPUnit\Framework\TestCase;

// Load the service
require_once __DIR__ . '/../../lib/Services/AttachmentService.php';

/**
 * Unit Tests for AttachmentService
 *
 * Tests attachment download logic including:
 * - Happy path: file lookup and base64 encoding
 * - Validation: invalid file IDs
 * - Security: file not found in AttachmentFile::lookup
 * - Content encoding correctness
 */
class AttachmentServiceTest extends TestCase
{
    private AttachmentService $service;

    protected function setUp(): void
    {
        AttachmentFile::$mockData = [];
        $this->service = AttachmentService::getInstance();
    }

    /**
     * Test successful attachment download returns correct structure
     */
    public function testDownloadAttachmentReturnsCorrectData(): void
    {
        $content = 'Hello, this is test file content!';
        $file = new AttachmentFile([
            'id' => 42,
            'name' => 'test-document.pdf',
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
            'content' => $content,
        ]);
        AttachmentFile::$mockData[42] = $file;

        $result = $this->service->downloadAttachment(42);

        $this->assertEquals(42, $result['file_id']);
        $this->assertEquals('test-document.pdf', $result['filename']);
        $this->assertEquals('application/pdf', $result['mime_type']);
        $this->assertEquals(strlen($content), $result['size']);
        $this->assertEquals(base64_encode($content), $result['content']);
    }

    /**
     * Test that base64 content can be decoded back to original
     */
    public function testDownloadAttachmentBase64IsDecodable(): void
    {
        $originalContent = "\x89PNG\r\n\x1a\nfake-binary-data";
        $file = new AttachmentFile([
            'id' => 43,
            'name' => 'image.png',
            'mime_type' => 'image/png',
            'size' => strlen($originalContent),
            'content' => $originalContent,
        ]);
        AttachmentFile::$mockData[43] = $file;

        $result = $this->service->downloadAttachment(43);

        $decoded = base64_decode($result['content']);
        $this->assertEquals($originalContent, $decoded);
    }

    /**
     * Test 400 error for zero file ID
     */
    public function testDownloadAttachmentRejectsZeroFileId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid file ID');

        $this->service->downloadAttachment(0);
    }

    /**
     * Test 400 error for negative file ID
     */
    public function testDownloadAttachmentRejectsNegativeFileId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid file ID');

        $this->service->downloadAttachment(-5);
    }

    /**
     * Test 404 error when file not found in lookup
     */
    public function testDownloadAttachmentReturns404WhenFileNotFound(): void
    {
        // No file registered in mockData for ID 999

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('File not found');

        $this->service->downloadAttachment(999);
    }

    /**
     * Test 500 error when file content is empty (null)
     */
    public function testDownloadAttachmentReturns500WhenContentIsNull(): void
    {
        $file = new AttachmentFile([
            'id' => 44,
            'name' => 'broken.dat',
            'mime_type' => 'application/octet-stream',
            'size' => 100,
            'content' => null,
        ]);
        AttachmentFile::$mockData[44] = $file;

        $this->expectException(Exception::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Failed to read file content');

        $this->service->downloadAttachment(44);
    }

    /**
     * Test 500 error when file content is false
     */
    public function testDownloadAttachmentReturns500WhenContentIsFalse(): void
    {
        $file = new AttachmentFile([
            'id' => 45,
            'name' => 'corrupt.dat',
            'mime_type' => 'application/octet-stream',
            'size' => 100,
            'content' => false,
        ]);
        AttachmentFile::$mockData[45] = $file;

        $this->expectException(Exception::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Failed to read file content');

        $this->service->downloadAttachment(45);
    }

    /**
     * Test successful download with empty string content (0 bytes is valid)
     */
    public function testDownloadAttachmentAcceptsEmptyStringContent(): void
    {
        $file = new AttachmentFile([
            'id' => 46,
            'name' => 'empty.txt',
            'mime_type' => 'text/plain',
            'size' => 0,
            'content' => '',
        ]);
        AttachmentFile::$mockData[46] = $file;

        $result = $this->service->downloadAttachment(46);

        $this->assertEquals(46, $result['file_id']);
        $this->assertEquals('empty.txt', $result['filename']);
        $this->assertEquals('', base64_decode($result['content']));
    }
}
