<?php
/**
 * API File: Ticket Update Endpoint
 *
 * Provides PATCH/PUT endpoint for updating tickets via API
 *
 * This file should be deployed to: /api/tickets-update.php
 * URL pattern: /api/tickets-update.php/{ticket_number}.json
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
    'Invalid URL format. Expected: /api/tickets-update.php/{ticket_number}.json'
);

// Only accept PATCH or PUT methods
$bootstrap->requireMethod(['PATCH', 'PUT']);

// Parse JSON body
$data = $bootstrap->parseJsonBody(required: true);

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params, $data) {
    $controller = $bootstrap->getController();
    $ticket = $controller->update($params['number'], $data);

    // Return ticket number as plain text for backwards compatibility
    return $ticket->getNumber();
});
