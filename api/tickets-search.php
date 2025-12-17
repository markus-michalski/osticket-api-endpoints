<?php
/**
 * API File: Ticket Search Endpoint
 *
 * Provides GET endpoint for searching tickets via API
 *
 * This file should be deployed to: /api/tickets-search.php
 * URL pattern: /api/tickets-search.php?query=...&status=...&limit=20
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse optional format from path info
$bootstrap->parsePathInfo('#^(?:\.(?P<format>json|xml))?$#');

// Only accept GET method
$bootstrap->requireMethod('GET');

// Parse query parameters with defaults
$params = $bootstrap->parseQueryParams([
    'limit' => 20,
    'offset' => 0,
    'sort' => 'created',
]);

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap, $params) {
    $controller = $bootstrap->getController();
    return $controller->searchTickets($params);
});
