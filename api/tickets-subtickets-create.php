<?php
/**
 * API File: Create Subticket Link Endpoint
 *
 * Provides POST endpoint for creating parent-child relationships between tickets
 *
 * This file should be deployed to: /api/tickets-subtickets-create.php
 * URL pattern: /api/tickets-subtickets-create.php/{parent_ticket_number}.json
 *
 * Requirements:
 * - API key with can_manage_subtickets permission
 * - Subticket Manager Plugin installed and active
 *
 * POST Body (JSON):
 * {
 *   "childId": "680285"
 * }
 *
 * Response Format (Success):
 * {
 *   "success": true,
 *   "message": "Subticket relationship created successfully",
 *   "parent": {
 *     "ticket_id": 100,
 *     "number": "680284",
 *     "subject": "Parent Ticket",
 *     "status": "Open"
 *   },
 *   "child": {
 *     "ticket_id": 101,
 *     "number": "680285",
 *     "subject": "Child Ticket",
 *     "status": "Open"
 *   }
 * }
 *
 * Error Codes:
 * - 400: Invalid ticket numbers or self-link attempt
 * - 403: API key not authorized (permission or department access)
 * - 404: Parent or child ticket not found
 * - 409: Child already has a parent
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

// Parse path info to get parent ticket number
// URL: /api/tickets-subtickets-create.php/680284.json -> path_info = /680284.json
$path_info = Osticket::get_path_info();

// Extract ticket number and format
// Pattern: /{ticket_number}.{format}
if (!preg_match('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-subtickets-create.php/{parent_ticket_number}.json', 'text/plain');
    exit;
}

$parentNumber = $matches['number'];
$format = $matches['format'];

// Only accept POST method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    Http::response(405, 'Method Not Allowed. Use POST', 'text/plain');
    exit;
}

// Parse JSON body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    Http::response(400, 'Invalid JSON in request body', 'text/plain');
    exit;
}

// Extract child ticket number from POST body
$childNumber = isset($data['childId']) ? (string)$data['childId'] : '';

if (empty($childNumber)) {
    Http::response(400, 'Missing childId in request body', 'text/plain');
    exit;
}

// Create controller and create subticket link
try {
    $controller = new SubticketApiController(null);
    $result = $controller->createLink($parentNumber, $childNumber);

    // Return success (format is always JSON for POST)
    Http::response(200, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 400; // Fallback to Bad Request for invalid codes
    }
    Http::response($code, $e->getMessage(), 'text/plain');
}
