<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for Attachment Metadata in Ticket Thread Entries
 *
 * Verifies that getTicket() returns attachment metadata for thread entries
 * that have file attachments, and that entries without attachments
 * remain unchanged (backward compatibility).
 */
class AttachmentMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        Ticket::$mockData = [];
        Ticket::$mockDataByNumber = [];
        API::$mockData = [];
        Dept::$mockData = [];
        Topic::$mockData = [];
        Priority::$mockData = [];
        TicketStatus::$mockData = [];
        Staff::$mockData = [];
        Team::$mockData = [];
        SLA::$mockData = [];
        AttachmentFile::$mockData = [];
    }

    /**
     * Test that thread entries with attachments include attachment metadata
     */
    public function testThreadEntryWithAttachmentsIncludesMetadata(): void
    {
        // Setup: Create mock attachment file
        $file = new AttachmentFile([
            'id' => 42,
            'name' => 'report.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'content' => 'fake-pdf-content',
        ]);
        AttachmentFile::$mockData[42] = $file;

        // Create attachment linked to the file
        $attachment = new Attachment([
            'id' => 10,
            'file' => $file,
            'inline' => false,
        ]);

        // Create GenericAttachments collection
        $attachments = new GenericAttachments([$attachment]);

        // Create thread entry WITH attachments
        $thread = new Thread([
            new ThreadEntry([
                'id' => 1,
                'type' => 'M',
                'poster' => 'Test User',
                'body' => 'Please see attached report',
                'user_id' => 10,
                'attachments' => $attachments,
            ]),
        ]);

        $ticket = new Ticket([
            'id' => 100,
            'number' => '900001',
            'subject' => 'Ticket with attachment',
            'thread' => $thread,
        ]);
        Ticket::$mockDataByNumber['900001'] = $ticket;
        Ticket::$mockData[100] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true,
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('900001');

        // Assert: Thread entry has attachment metadata
        $entry = $result['thread'][0];
        $this->assertArrayHasKey('attachments', $entry);
        $this->assertArrayHasKey('attachment_count', $entry);
        $this->assertEquals(1, $entry['attachment_count']);

        // Assert: Attachment metadata structure
        $att = $entry['attachments'][0];
        $this->assertEquals(10, $att['id']);
        $this->assertEquals(42, $att['file_id']);
        $this->assertEquals('report.pdf', $att['filename']);
        $this->assertEquals(1024, $att['size']);
        $this->assertEquals('application/pdf', $att['mime_type']);
        $this->assertFalse($att['inline']);
    }

    /**
     * Test that thread entries without attachments do NOT have attachment keys
     */
    public function testThreadEntryWithoutAttachmentsHasNoMetadata(): void
    {
        $thread = new Thread([
            new ThreadEntry([
                'id' => 1,
                'type' => 'M',
                'poster' => 'Test User',
                'body' => 'Just a text message',
                'user_id' => 10,
                // No attachments
            ]),
        ]);

        $ticket = new Ticket([
            'id' => 101,
            'number' => '900002',
            'subject' => 'Ticket without attachment',
            'thread' => $thread,
        ]);
        Ticket::$mockDataByNumber['900002'] = $ticket;
        Ticket::$mockData[101] = $ticket;

        $apiKey = new API([
            'key' => 'test-api-key',
            'can_read_tickets' => true,
        ]);
        API::$mockData['test-key'] = $apiKey;

        // Execute
        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('900002');

        // Assert: No attachment keys present
        $entry = $result['thread'][0];
        $this->assertArrayNotHasKey('attachments', $entry);
        $this->assertArrayNotHasKey('attachment_count', $entry);
    }

    /**
     * Test multiple attachments on a single thread entry
     */
    public function testThreadEntryWithMultipleAttachments(): void
    {
        $file1 = new AttachmentFile([
            'id' => 50,
            'name' => 'image.png',
            'mime_type' => 'image/png',
            'size' => 2048,
            'content' => 'fake-png',
        ]);
        $file2 = new AttachmentFile([
            'id' => 51,
            'name' => 'document.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 5120,
            'content' => 'fake-docx',
        ]);
        AttachmentFile::$mockData[50] = $file1;
        AttachmentFile::$mockData[51] = $file2;

        $attachments = new GenericAttachments([
            new Attachment(['id' => 20, 'file' => $file1, 'inline' => false]),
            new Attachment(['id' => 21, 'file' => $file2, 'inline' => false]),
        ]);

        $thread = new Thread([
            new ThreadEntry([
                'id' => 1,
                'type' => 'M',
                'poster' => 'User',
                'body' => 'Two files attached',
                'user_id' => 10,
                'attachments' => $attachments,
            ]),
        ]);

        $ticket = new Ticket([
            'id' => 102,
            'number' => '900003',
            'subject' => 'Multiple attachments',
            'thread' => $thread,
        ]);
        Ticket::$mockDataByNumber['900003'] = $ticket;
        Ticket::$mockData[102] = $ticket;

        $apiKey = new API(['key' => 'test-api-key', 'can_read_tickets' => true]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('900003');

        $entry = $result['thread'][0];
        $this->assertEquals(2, $entry['attachment_count']);
        $this->assertCount(2, $entry['attachments']);
        $this->assertEquals('image.png', $entry['attachments'][0]['filename']);
        $this->assertEquals('document.docx', $entry['attachments'][1]['filename']);
    }

    /**
     * Test that inline attachments are included with inline flag set to true
     *
     * All attachments are returned — the consumer can filter by inline flag
     */
    public function testInlineAttachmentsIncludedWithFlag(): void
    {
        $inlineFile = new AttachmentFile([
            'id' => 60,
            'name' => 'embedded.png',
            'mime_type' => 'image/png',
            'size' => 512,
            'content' => 'fake',
        ]);
        $normalFile = new AttachmentFile([
            'id' => 61,
            'name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 4096,
            'content' => 'fake',
        ]);
        AttachmentFile::$mockData[60] = $inlineFile;
        AttachmentFile::$mockData[61] = $normalFile;

        $attachments = new GenericAttachments([
            new Attachment(['id' => 30, 'file' => $inlineFile, 'inline' => true]),
            new Attachment(['id' => 31, 'file' => $normalFile, 'inline' => false]),
        ]);

        $thread = new Thread([
            new ThreadEntry([
                'id' => 1,
                'type' => 'M',
                'poster' => 'User',
                'body' => 'Mixed attachments',
                'user_id' => 10,
                'attachments' => $attachments,
            ]),
        ]);

        $ticket = new Ticket([
            'id' => 103,
            'number' => '900004',
            'subject' => 'Inline vs separate',
            'thread' => $thread,
        ]);
        Ticket::$mockDataByNumber['900004'] = $ticket;
        Ticket::$mockData[103] = $ticket;

        $apiKey = new API(['key' => 'test-api-key', 'can_read_tickets' => true]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('900004');

        $entry = $result['thread'][0];
        // Both attachments are returned with correct inline flags
        $this->assertEquals(2, $entry['attachment_count']);
        $this->assertCount(2, $entry['attachments']);
        $this->assertEquals('embedded.png', $entry['attachments'][0]['filename']);
        $this->assertTrue($entry['attachments'][0]['inline']);
        $this->assertEquals('contract.pdf', $entry['attachments'][1]['filename']);
        $this->assertFalse($entry['attachments'][1]['inline']);
    }

    /**
     * Test thread entry with empty GenericAttachments (no files attached)
     */
    public function testThreadEntryWithEmptyAttachmentsCollection(): void
    {
        $attachments = new GenericAttachments([]);

        $thread = new Thread([
            new ThreadEntry([
                'id' => 1,
                'type' => 'M',
                'poster' => 'User',
                'body' => 'Empty attachments',
                'user_id' => 10,
                'attachments' => $attachments,
            ]),
        ]);

        $ticket = new Ticket([
            'id' => 104,
            'number' => '900005',
            'subject' => 'Empty attachments collection',
            'thread' => $thread,
        ]);
        Ticket::$mockDataByNumber['900005'] = $ticket;
        Ticket::$mockData[104] = $ticket;

        $apiKey = new API(['key' => 'test-api-key', 'can_read_tickets' => true]);
        API::$mockData['test-key'] = $apiKey;

        $controller = new ExtendedTicketApiController();
        $result = $controller->getTicket('900005');

        // Empty collection = no attachment keys
        $entry = $result['thread'][0];
        $this->assertArrayNotHasKey('attachments', $entry);
        $this->assertArrayNotHasKey('attachment_count', $entry);
    }
}
