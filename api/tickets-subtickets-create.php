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
 *   "parent": { ... },
 *   "child": { ... }
 * }
 *
 * Error Codes:
 * - 400: Invalid ticket numbers, missing childId, or self-link attempt
 * - 403: API key not authorized (permission or department access)
 * - 404: Parent or child ticket not found
 * - 422: Child already has a parent (use 422 because osTicket doesn't support 409)
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse parent ticket number and format from path info
$params = $bootstrap->parsePathInfo(
    '#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#',
    'Invalid URL format. Expected: /api/tickets-subtickets-create.php/{parent_ticket_number}.json'
);

// Only accept POST method
$bootstrap->requireMethod('POST');

// Parse JSON body
$data = $bootstrap->parseJsonBody(required: true);

// Validate childId
$childNumber = isset($data['childId']) ? (string)$data['childId'] : '';
if (empty($childNumber)) {
    ApiBootstrap::sendErrorResponse(400, 'Missing childId in request body');
}

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params, $childNumber) {
    $controller = $bootstrap->getSubticketController();
    return $controller->createLink($params['number'], $childNumber);
});
