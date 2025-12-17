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

// Load osTicket bootstrap FIRST (must be relative to THIS file's location)
require_once '../main.inc.php';

// Then load ApiBootstrap helper
require_once INCLUDE_DIR . 'plugins/api-endpoints/lib/ApiBootstrap.php';

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
