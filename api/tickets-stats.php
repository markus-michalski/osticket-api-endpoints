<?php
/**
 * API File: Ticket Statistics Endpoint
 *
 * Provides GET endpoint for retrieving ticket statistics via API
 *
 * This file should be deployed to: /api/tickets-stats.php
 * URL pattern: /api/tickets-stats.php[.json|.xml]
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
    return $controller->getTicketStats();
});
