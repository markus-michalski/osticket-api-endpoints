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
 *   "child": { ... }
 * }
 *
 * Error Codes:
 * - 400: Invalid child ticket number
 * - 403: API key not authorized (permission or department access)
 * - 404: Child ticket not found or child has no parent
 */

declare(strict_types=1);

// Load osTicket bootstrap FIRST (must be relative to THIS file's location)
require_once '../main.inc.php';

// Then load ApiBootstrap helper
require_once INCLUDE_DIR . 'plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse child ticket number and format from path info
$params = $bootstrap->parsePathInfo(
    '#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#',
    'Invalid URL format. Expected: /api/tickets-subtickets-unlink.php/{child_ticket_number}.json'
);

// Only accept DELETE method
$bootstrap->requireMethod('DELETE');

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params) {
    $controller = $bootstrap->getSubticketController();
    return $controller->unlinkChild($params['number']);
});
