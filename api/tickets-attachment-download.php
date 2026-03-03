<?php
/**
 * API Endpoint: Attachment Download
 *
 * Downloads ticket attachment content as base64-encoded JSON response.
 *
 * URL: GET /api/tickets-attachment-download.php/{file_id}.json
 * Permission: can_read_tickets
 */

declare(strict_types=1);

// Load osTicket bootstrap
require_once '../main.inc.php';

// Load ApiBootstrap helper
require_once INCLUDE_DIR . 'plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse file_id from path info
$params = $bootstrap->parsePathInfo(
    '#^/(?P<file_id>\d+)\.(?P<format>json|xml)$#',
    'Invalid URL format. Expected: /api/tickets-attachment-download.php/{file_id}.json'
);

$bootstrap->requireMethod('GET');

$bootstrap->execute(function () use ($bootstrap, $params) {
    $controller = $bootstrap->getController();
    $fileId = (int)$params['file_id'];
    return $controller->downloadAttachment($fileId);
});
