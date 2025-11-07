<?php
/**
 * API File: Create Subticket Link Endpoint
 *
 * Provides POST endpoint for creating parent-child relationships between tickets
 *
 * This file should be deployed to: /api/tickets-subtickets-create.php
 * URL: /api/tickets-subtickets-create.php
 *
 * Requirements:
 * - API key with can_manage_subtickets permission
 * - Subticket Manager Plugin installed and active
 *
 * POST Body (JSON):
 * {
 *   "parent_id": 100,
 *   "child_id": 200
 * }
 *
 * Response Format (Success):
 * {
 *   "success": true,
 *   "message": "Subticket relationship created successfully",
 *   "parent": {
 *     "ticket_id": 100,
 *     "number": "ABC100",
 *     "subject": "Parent Ticket",
 *     "status": "Open"
 *   },
 *   "child": {
 *     "ticket_id": 200,
 *     "number": "ABC200",
 *     "subject": "Child Ticket",
 *     "status": "Open"
 *   }
 * }
 *
 * Error Codes:
 * - 400: Invalid ticket IDs or self-link attempt
 * - 403: API key not authorized (permission or department access)
 * - 404: Parent or child ticket not found
 * - 409: Child already has a parent
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
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
}

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

// Extract parent_id and child_id
$parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : 0;
$childId = isset($data['child_id']) ? (int)$data['child_id'] : 0;

// Create controller and create subticket link
try {
    $controller = new SubticketApiController(null);
    $result = $controller->createLink($parentId, $childId);

    // Return success
    Http::response(200, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 400; // Fallback to Bad Request for invalid codes
    }
    Http::response($code, $e->getMessage(), 'text/plain');
}
