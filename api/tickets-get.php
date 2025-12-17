<?php
/**
 * API File: Ticket Get Endpoint
 *
 * Provides GET endpoint for retrieving tickets via API
 *
 * This file should be deployed to: /api/tickets-get.php
 * URL pattern: /api/tickets-get.php/{ticket_number}.json
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse ticket number and format from path info
$params = $bootstrap->parsePathInfo(
    '#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#',
    'Invalid URL format. Expected: /api/tickets-get.php/{ticket_number}.json'
);

// Only accept GET method
$bootstrap->requireMethod('GET');

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params) {
    $controller = $bootstrap->getController();
    return $controller->getTicket($params['number']);
});
