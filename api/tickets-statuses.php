<?php
/**
 * API File: Ticket Statuses Endpoint
 *
 * Provides GET endpoint for retrieving all ticket statuses from database
 * This allows API clients to dynamically lookup status IDs by name
 *
 * This file should be deployed to: /api/tickets-statuses.php
 * URL pattern: /api/tickets-statuses.php[.json|.xml]
 *
 * Returns: Array of status objects with id, name, and state
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/plugins/api-endpoints/lib/ApiBootstrap.php';

$bootstrap = ApiBootstrap::initialize();

// Parse optional format from path info
$bootstrap->parsePathInfo('#^(?:\.(?P<format>json|xml))?$#');

// Only accept GET method
$bootstrap->requireMethod('GET');

// Execute with standardized error handling
$bootstrap->execute(function() use ($bootstrap) {
    $controller = $bootstrap->getController();
    return $controller->getTicketStatuses();
});
