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
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
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

    // Return statuses as JSON
    Http::response(200, json_encode($statuses), 'application/json');

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode() ?: 400;
    if ($code == 401) {
        Http::response(401, $e->getMessage(), 'text/plain');
    } else {
        Http::response(400, $e->getMessage(), 'text/plain');
    }
}
