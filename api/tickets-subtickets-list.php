<?php
/**
 * API File: Get Children Tickets (List) Endpoint
 *
 * Provides GET endpoint for retrieving all child tickets (subtickets) of a parent ticket
 *
 * This file should be deployed to: /api/tickets-subtickets-list.php
 * URL pattern: /api/tickets-subtickets-list.php/{parent_ticket_number}.json
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
 * - 400: Invalid ticket number
 * - 403: API key not authorized for subticket operations
 * - 404: Parent ticket not found
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse parent ticket number and format from path info
$params = $bootstrap->parsePathInfo(
    '#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#',
    'Invalid URL format. Expected: /api/tickets-subtickets-list.php/{parent_ticket_number}.json'
);

// Only accept GET method
$bootstrap->requireMethod('GET');

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params) {
    $controller = $bootstrap->getSubticketController();
    return $controller->getList($params['number']);
});
