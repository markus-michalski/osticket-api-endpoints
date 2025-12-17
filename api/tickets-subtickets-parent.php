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

declare(strict_types=1);

// Load osTicket bootstrap FIRST (must be relative to THIS file's location)
require_once '../main.inc.php';

// Then load ApiBootstrap helper
require_once INCLUDE_DIR . 'plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse child ticket number and format from path info
$params = $bootstrap->parsePathInfo(
    '#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#',
    'Invalid URL format. Expected: /api/tickets-subtickets-parent.php/{child_ticket_number}.json'
);

// Only accept GET method
$bootstrap->requireMethod('GET');

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params) {
    $controller = $bootstrap->getSubticketController();
    return $controller->getParent($params['number']);
});
