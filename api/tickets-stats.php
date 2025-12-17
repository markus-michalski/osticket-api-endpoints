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

// Load osTicket bootstrap FIRST (must be relative to THIS file's location)
require_once '../main.inc.php';

// Then load ApiBootstrap helper
require_once INCLUDE_DIR . 'plugins/api-endpoints/lib/ApiBootstrap.php';

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
