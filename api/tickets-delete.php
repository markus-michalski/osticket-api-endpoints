<?php
/**
 * API File: Ticket Delete Endpoint
 *
 * Provides DELETE endpoint for deleting tickets via API
 *
 * This file should be deployed to: /api/tickets-delete.php
 * URL pattern: /api/tickets-delete.php/{ticket_number}.json
 */

// Require API bootstrap
require_once('../main.inc.php');

if (!defined('INCLUDE_DIR'))
    die('Fatal Error: Cannot access API outside of osTicket');

require_once(INCLUDE_DIR.'class.api.php');
require_once(INCLUDE_DIR.'class.ticket.php');

// Load our controller
$plugin_path = INCLUDE_DIR.'plugins/api-endpoints/';
if (file_exists($plugin_path.'controllers/ExtendedTicketApiController.php')) {
    require_once($plugin_path.'controllers/ExtendedTicketApiController.php');
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
}

// Parse path info to get ticket number
// URL: /api/tickets-delete.php/680284.json -> path_info = /680284.json
$path_info = Osticket::get_path_info();

// Extract ticket number and format
// Pattern: /{ticket_number}.{format}
if (!preg_match('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-delete.php/{ticket_number}.json', 'text/plain');
    exit;
}

$ticket_number = $matches['number'];
$format = $matches['format'];

// Only accept DELETE method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'DELETE') {
    Http::response(405, 'Method Not Allowed. Use DELETE', 'text/plain');
    exit;
}

// Create controller and handle delete
try {
    $controller = new ExtendedTicketApiController(null);
    $deletedNumber = $controller->deleteTicket($ticket_number);

    // Return success with deleted ticket number
    Http::response(200, $deletedNumber, 'text/plain');

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode() ?: 400;
    // Validate HTTP status code
    if ($code < 100 || $code > 599) {
        $code = 400;
    }
    if ($code == 401) {
        Http::response(401, $e->getMessage(), 'text/plain');
    } elseif ($code == 404) {
        Http::response(404, $e->getMessage(), 'text/plain');
    } else {
        Http::response(400, $e->getMessage(), 'text/plain');
    }
}
