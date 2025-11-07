<?php
/**
 * API File: Get Children Tickets (List) Endpoint
 *
 * Provides GET endpoint for retrieving all child tickets (subtickets) of a parent ticket
 *
 * This file should be deployed to: /api/tickets-subtickets-list.php
 * URL pattern: /api/tickets-subtickets-list.php/{parent_ticket_id}.json
 *
 * Requirements:
 * - API key with can_manage_subtickets permission
 * - Subticket Manager Plugin installed and active
 *
 * Response Format (With Children):
 * {
 *   "children": [
 *     {
 *       "ticket_id": 123,
 *       "number": "ABC123",
 *       "subject": "Child Ticket 1",
 *       "status": "Open"
 *     },
 *     {
 *       "ticket_id": 124,
 *       "number": "ABC124",
 *       "subject": "Child Ticket 2",
 *       "status": "Closed"
 *     }
 *   ]
 * }
 *
 * Response Format (No Children):
 * {
 *   "children": []
 * }
 *
 * Error Codes:
 * - 400: Invalid ticket ID
 * - 403: API key not authorized for subticket operations
 * - 404: Parent ticket not found
 * - 501: Subticket plugin not available
 */

// Require API bootstrap
require_once('../main.inc.php');

if (!defined('INCLUDE_DIR'))
    die('Fatal Error: Cannot access API outside of osTicket');

require_once(INCLUDE_DIR.'class.api.php');
require_once(INCLUDE_DIR.'class.ticket.php');

// Load SubticketApiController
$plugin_path = INCLUDE_DIR.'plugins/api-endpoints/';
if (file_exists($plugin_path.'controllers/SubticketApiController.php')) {
    require_once($plugin_path.'controllers/SubticketApiController.php');
    require_once($plugin_path.'lib/XmlHelper.php');
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
}

// Parse path info to get parent ticket ID
// URL: /api/tickets-subtickets-list.php/100.json -> path_info = /100.json
$path_info = Osticket::get_path_info();

// Extract ticket ID and format
// Pattern: /{ticket_id}.{format}
if (!preg_match('#^/(?P<id>\d+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-subtickets-list.php/{parent_ticket_id}.json', 'text/plain');
    exit;
}

$parentId = (int)$matches['id'];
$format = $matches['format'];

// Only accept GET method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    Http::response(405, 'Method Not Allowed. Use GET', 'text/plain');
    exit;
}

// Create controller and get children list
try {
    $controller = new SubticketApiController(null);
    $result = $controller->getList($parentId);

    // Return success with children data
    if ($format === 'json') {
        Http::response(200, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');
    } else {
        // XML format
        $xml = XmlHelper::arrayToXml($result, 'response');
        Http::response(200, $xml, 'application/xml');
    }

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 400; // Fallback to Bad Request for invalid codes
    }
    Http::response($code, $e->getMessage(), 'text/plain');
}

