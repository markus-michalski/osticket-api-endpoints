<?php
/**
 * API File: Ticket Update Endpoint
 *
 * Provides PATCH/PUT endpoint for updating tickets via API
 *
 * This file should be deployed to: /api/tickets-update.php
 * URL pattern: /api/tickets-update.php/{ticket_number}.json
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
// URL: /api/tickets-update.php/680284.json -> path_info = /680284.json
$path_info = Osticket::get_path_info();

// Extract ticket number and format
// Pattern: /{ticket_number}.{format}
if (!preg_match('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-update.php/{ticket_number}.json', 'text/plain');
    exit;
}

$ticket_number = $matches['number'];
$format = $matches['format'];

// Only accept PATCH or PUT methods
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['PATCH', 'PUT'], true)) { // Strict comparison for type safety
    Http::response(405, 'Method Not Allowed. Use PATCH or PUT', 'text/plain');
    exit;
}

// Parse JSON body
$data = null;
if (isset($_SERVER['CONTENT_TYPE']) &&
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $body = file_get_contents('php://input');

    // Security: Limit JSON depth to prevent JSON bomb DoS attacks
    try {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        Http::response(400, json_encode([
            'error' => true,
            'message' => 'Invalid JSON: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE), 'application/json');
        exit;
    }

    if (empty($data)) {
        Http::response(400, json_encode([
            'error' => true,
            'message' => 'No data provided in JSON body'
        ], JSON_UNESCAPED_UNICODE), 'application/json');
        exit;
    }
} elseif ($_POST) {
    $data = $_POST;
} else {
    Http::response(400, json_encode([
        'error' => true,
        'message' => 'No data provided'
    ], JSON_UNESCAPED_UNICODE), 'application/json');
    exit;
}

// Create controller and handle update
try {
    $controller = new ExtendedTicketApiController(null);
    $ticket = $controller->update($ticket_number, $data);

    // Return success with ticket number
    Http::response(200, $ticket->getNumber(), 'text/plain');

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
