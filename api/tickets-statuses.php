<?php
/**
 * API File: Ticket Statuses Endpoint
 *
 * Provides GET endpoint for retrieving all ticket statuses from database
 * This allows API clients to dynamically lookup status IDs by name
 *
 * This file should be deployed to: /api/tickets-statuses.php
 * URL pattern: /api/tickets-statuses.php
 *
 * Returns: Array of status objects with id, name, and state
 * Example:
 * [
 *   {"id": 1, "name": "Open", "state": "open"},
 *   {"id": 2, "name": "Resolved", "state": "closed"},
 *   {"id": 3, "name": "Closed", "state": "closed"}
 * ]
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

// Parse path info to get format
// URL: /api/tickets-statuses.php -> path_info = empty
// URL: /api/tickets-statuses.php.json -> path_info = .json
$path_info = Osticket::get_path_info();

// Extract format (default: json)
$format = 'json';
if (preg_match('#\.(?P<format>json|xml)$#', $path_info, $matches)) {
    $format = $matches['format'];
}

// Only accept GET method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    Http::response(405, 'Method Not Allowed. Use GET', 'text/plain');
    exit;
}

// Create controller and get statuses
try {
    $controller = new ExtendedTicketApiController(null);
    $statuses = $controller->getTicketStatuses();

    // Return success with statuses
    if ($format === 'json') {
        Http::response(200, json_encode($statuses, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');
    } else {
        // XML format
        $xml = XmlHelper::arrayToXml(array('statuses' => $statuses), 'result');
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
    } else {
        Http::response(400, $e->getMessage(), 'text/plain');
    }
}
