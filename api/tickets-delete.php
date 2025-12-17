<?php
/**
 * API File: Ticket Delete Endpoint
 *
 * Provides DELETE endpoint for permanently removing tickets via API
 *
 * This file should be deployed to: /api/tickets-delete.php
 * URL pattern: /api/tickets-delete.php/{ticket_number}.json
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse ticket number and format from path info
$params = $bootstrap->parsePathInfo(
    '#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#',
    'Invalid URL format. Expected: /api/tickets-delete.php/{ticket_number}.json'
);

// Only accept DELETE method
$bootstrap->requireMethod('DELETE');

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params) {
    $controller = $bootstrap->getController();
    $deletedNumber = $controller->deleteTicket($params['number']);

    // Return deleted ticket number as plain text
    return $deletedNumber;
});
