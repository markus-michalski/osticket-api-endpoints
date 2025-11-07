<?php
/**
 * API File: Ticket Get Endpoint
 *
 * Provides GET endpoint for retrieving tickets via API
 *
 * This file should be deployed to: /api/tickets-get.php
 * URL pattern: /api/tickets-get.php/{ticket_number}.json
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
    require_once($plugin_path.'lib/XmlHelper.php');
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
}

// Parse path info to get ticket number
// URL: /api/tickets-get.php/680284.json -> path_info = /680284.json
$path_info = Osticket::get_path_info();

// DEBUG: Log what we actually got
error_log('[tickets-get.php] PATH_INFO received: ' . $path_info);
error_log('[tickets-get.php] REQUEST_URI: ' . $_SERVER['REQUEST_URI']);

// Extract ticket number and format
// Pattern: /{ticket_number}.{format}
if (!preg_match('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    error_log('[tickets-get.php] Pattern did NOT match path_info: ' . $path_info);
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-get.php/{ticket_number}.json (got: ' . $path_info . ')', 'text/plain');
    exit;
}

$ticket_number = $matches['number'];
$format = $matches['format'];

// Only accept GET method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    Http::response(405, 'Method Not Allowed. Use GET', 'text/plain');
    exit;
}

// Create controller and handle retrieval
try {
    $controller = new ExtendedTicketApiController(null);
    $ticketData = $controller->getTicket($ticket_number);

    // Return success with ticket data
    if ($format === 'json') {
        Http::response(200, json_encode($ticketData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');
    } else {
        // XML format
        $xml = XmlHelper::arrayToXml($ticketData, 'ticket');
        Http::response(200, $xml, 'application/xml');
    }

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

