<?php
/**
 * API File: Unlink Subticket Endpoint
 *
 * Provides DELETE endpoint for removing parent-child relationships between tickets
 *
 * This file should be deployed to: /api/tickets-subtickets-unlink.php
 * URL pattern: /api/tickets-subtickets-unlink.php/{child_ticket_number}.json
 *
 * Requirements:
 * - API key with can_manage_subtickets permission
 * - Subticket Manager Plugin installed and active
 *
 * Response Format (Success):
 * {
 *   "success": true,
 *   "message": "Subticket relationship removed successfully",
 *   "child": {
 *     "ticket_id": 200,
 *     "number": "680285",
 *     "subject": "Child Ticket",
 *     "status": "Open"
 *   }
 * }
 *
 * Error Codes:
 * - 400: Invalid child ticket number
 * - 403: API key not authorized (permission or department access)
 * - 404: Child ticket not found or child has no parent
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
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
}

// Parse path info to get child ticket number
// URL: /api/tickets-subtickets-unlink.php/680285.json -> path_info = /680285.json
$path_info = Osticket::get_path_info();

// Extract ticket number and format
// Pattern: /{ticket_number}.{format}
if (!preg_match('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-subtickets-unlink.php/{child_ticket_number}.json', 'text/plain');
    exit;
}

$childNumber = $matches['number'];
$format = $matches['format'];

// Only accept DELETE method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'DELETE') {
    Http::response(405, 'Method Not Allowed. Use DELETE', 'text/plain');
    exit;
}

// Create controller and unlink subticket
try {
    $controller = new SubticketApiController(null);
    $result = $controller->unlinkChild($childNumber);

    // Return success (format is always JSON for DELETE)
    Http::response(200, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 400; // Fallback to Bad Request for invalid codes
    }
    Http::response($code, $e->getMessage(), 'text/plain');
}
