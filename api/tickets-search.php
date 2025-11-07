<?php
/**
 * API File: Ticket Search Endpoint
 *
 * Provides GET endpoint for searching tickets via API
 *
 * This file should be deployed to: /api/tickets-search.php
 * URL pattern: /api/tickets-search.php?query=...&status=...&limit=20
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
// URL: /api/tickets-search.php -> path_info = empty
// URL: /api/tickets-search.php.json -> path_info = .json
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

// Extract query parameters
$params = array();

// Query (search term)
if (isset($_GET['query']) && trim($_GET['query']) !== '') {
    $params['query'] = trim($_GET['query']);
}

// Status filter (accept both ID and name)
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $params['status'] = $_GET['status']; // Keep as string, controller will handle ID vs name
}

// Department filter (accept both ID and name)
if (isset($_GET['department']) && $_GET['department'] !== '') {
    $params['department'] = $_GET['department']; // Keep as string, controller will handle ID vs name
}

// Limit (default: 20, max: 100)
if (isset($_GET['limit']) && $_GET['limit'] !== '') {
    $params['limit'] = (int)$_GET['limit'];
}

// Offset (pagination)
if (isset($_GET['offset']) && $_GET['offset'] !== '') {
    $params['offset'] = (int)$_GET['offset'];
}

// Sort (created, updated, number)
if (isset($_GET['sort']) && trim($_GET['sort']) !== '') {
    $params['sort'] = trim($_GET['sort']);
}

// Create controller and handle search
try {
    $controller = new ExtendedTicketApiController(null);
    $tickets = $controller->searchTickets($params);

    // Return success with tickets array
    if ($format === 'json') {
        Http::response(200, json_encode($tickets, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');
    } else {
        // XML format
        $xml = XmlHelper::arrayToXml(array('tickets' => $tickets), 'result');
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

