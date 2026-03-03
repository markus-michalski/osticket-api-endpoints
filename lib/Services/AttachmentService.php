<?php
/**
 * Attachment Service
 *
 * Handles attachment download with security validation.
 * Ensures file_id belongs to a ticket thread entry before serving content.
 */

declare(strict_types=1);

class AttachmentService
{
    /**
     * Download attachment by file ID
     *
     * Security: Verifies the file belongs to a ticket attachment
     * before returning content. Prevents arbitrary file access.
     *
     * @param int $fileId The file ID from ost_file table
     * @return array {file_id, filename, mime_type, size, content (base64)}
     * @throws Exception 400 if file ID invalid
     * @throws Exception 403 if file not linked to a ticket attachment
     * @throws Exception 404 if file not found
     * @throws Exception 500 if file content cannot be read
     */
    public function downloadAttachment(int $fileId): array
    {
        if ($fileId < 1) {
            throw new Exception('Invalid file ID', 400);
        }

        // Security: Verify file belongs to a ticket attachment (not arbitrary ost_file access)
        if (defined('ATTACHMENT_TABLE')) {
            $sql = sprintf(
                "SELECT 1 FROM %s WHERE file_id = %d LIMIT 1",
                ATTACHMENT_TABLE,
                $fileId
            );
            $result = db_query($sql);
            if (!$result || !db_fetch_array($result)) {
                throw new Exception('Attachment not accessible', 403);
            }
        }

        // Load file object
        $file = AttachmentFile::lookup($fileId);
        if (!$file) {
            throw new Exception('File not found', 404);
        }

        // Read file content (safe for files up to ~10 MB)
        $content = $file->getData();
        if ($content === false || $content === null) {
            throw new Exception('Failed to read file content', 500);
        }

        return [
            'file_id' => (int)$file->getId(),
            'filename' => $file->getName(),
            'mime_type' => $file->getMimeType(),
            'size' => (int)$file->getSize(),
            'content' => base64_encode($content),
        ];
    }

    // =========================================================================
    // Singleton Pattern
    // =========================================================================

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
