<?php
/**
 * API File: Get Parent Ticket Endpoint
 *
 * Provides GET endpoint for retrieving parent ticket of a subticket
 *
 * This file should be deployed to: /api/tickets-subtickets-parent.php
 * URL pattern: /api/tickets-subtickets-parent.php/{child_ticket_number}.json
 *
 * Requirements:
 * - API key with can_manage_subtickets permission
 * - Subticket Manager Plugin installed and active
 *
 * Response Format (Success):
 * {
 *   "parent": {
 *     "ticket_id": 100,
 *     "number": "ABC100",
 *     "subject": "Parent Ticket",
 *     "status": "Open"
 *   }
 * }
 *
 * Response Format (No Parent):
 * {
 *   "parent": null
 * }
 *
 * Error Codes:
 * - 400: Invalid ticket number
 * - 403: API key not authorized for subticket operations
 * - 404: Ticket not found
 */

// Require API bootstrap
require_once('../main.inc.php');

if (!defined('INCLUDE_DIR'))
    die('Fatal Error: Cannot access API outside of osTicket');

require_once(INCLUDE_DIR.'class.api.php');
require_once(INCLUDE_DIR.'class.ticket.php');

// Load SubticketApiController and XmlHelper
$plugin_path = INCLUDE_DIR.'plugins/api-endpoints/';
if (file_exists($plugin_path.'controllers/SubticketApiController.php')) {
    require_once($plugin_path.'controllers/SubticketApiController.php');
    require_once($plugin_path.'lib/XmlHelper.php');
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
}

// Parse path info to get child ticket number
// URL: /api/tickets-subtickets-parent.php/680284.json -> path_info = /680284.json
$path_info = Osticket::get_path_info();

// Extract ticket number and format
// Pattern: /{ticket_number}.{format}
if (!preg_match('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-subtickets-parent.php/{child_ticket_number}.json', 'text/plain');
    exit;
}

$childNumber = $matches['number'];
$format = $matches['format'];

// Only accept GET method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    Http::response(405, 'Method Not Allowed. Use GET', 'text/plain');
    exit;
}

// Create controller and get parent ticket
try {
    $controller = new SubticketApiController(null);
    $result = $controller->getParent($childNumber);

    // Return success with parent data
    if ($format === 'json') {
        $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        Http::response(200, $json, 'application/json');
    } else {
        // XML format
        $xml = XmlHelper::arrayToXml($result, 'response');
        Http::response(200, $xml, 'application/xml');
    }

} catch (Exception $e) {
    // Handle errors - validate HTTP status code
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 400; // Fallback to Bad Request for invalid codes
    }
    Http::response($code, $e->getMessage(), 'text/plain');
}
