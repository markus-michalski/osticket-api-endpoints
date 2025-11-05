<?php
/**
 * API File: Ticket Get Endpoint
 *
 * Provides GET endpoint for retrieving tickets via API
 *
 * This file should be deployed to: /api/tickets-get.php
 * URL pattern: /api/tickets-get.php/{ticket_number}.json
 */

// Require API bootstrap
require_once('../main.inc.php');

if (!defined('INCLUDE_DIR'))
    die('Fatal Error: Cannot access API outside of osTicket');

require_once(INCLUDE_DIR.'class.api.php');
require_once(INCLUDE_DIR.'class.ticket.php');

// Load our controller
$plugin_path = INCLUDE_DIR.'plugins/api-endpoints/';
if (file_exists($plugin_path.'controllers/ExtendedTicketApiController.php')) {
    require_once($plugin_path.'controllers/ExtendedTicketApiController.php');
} else {
    Http::response(500, 'API Endpoints Plugin not properly installed', 'text/plain');
    exit;
}

// Parse path info to get ticket number
// URL: /api/tickets-get.php/680284.json -> path_info = /680284.json
$path_info = Osticket::get_path_info();

// Extract ticket number and format
// Pattern: /{ticket_number}.{format}
if (!preg_match('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-get.php/{ticket_number}.json', 'text/plain');
    exit;
}

$ticket_number = $matches['number'];
$format = $matches['format'];

// Only accept GET method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    Http::response(405, 'Method Not Allowed. Use GET', 'text/plain');
    exit;
}

// Create controller and handle retrieval
try {
    $controller = new ExtendedTicketApiController(null);
    $ticketData = $controller->getTicket($ticket_number);

    // Return success with ticket data
    if ($format === 'json') {
        Http::response(200, json_encode($ticketData), 'application/json');
    } else {
        // XML format
        $xml = arrayToXml($ticketData, 'ticket');
        Http::response(200, $xml, 'application/xml');
    }

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode() ?: 400;
    if ($code == 401) {
        Http::response(401, $e->getMessage(), 'text/plain');
    } elseif ($code == 404) {
        Http::response(404, $e->getMessage(), 'text/plain');
    } else {
        Http::response(400, $e->getMessage(), 'text/plain');
    }
}

/**
 * Convert array to XML
 */
function arrayToXml($data, $rootElement = 'root') {
    $xml = new SimpleXMLElement("<$rootElement/>");
    arrayToXmlRecursive($data, $xml);
    return $xml->asXML();
}

/**
 * Recursive helper for array to XML conversion
 */
function arrayToXmlRecursive($data, &$xml) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            $subnode = $xml->addChild($key);
            arrayToXmlRecursive($value, $subnode);
        } else {
            $xml->addChild($key, htmlspecialchars($value));
        }
    }
}
