<?php
/**
 * API File: Get Parent Ticket Endpoint
 *
 * Provides GET endpoint for retrieving parent ticket of a subticket
 *
 * This file should be deployed to: /api/tickets-subtickets-parent.php
 * URL pattern: /api/tickets-subtickets-parent.php/{child_ticket_id}.json
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
 * - 400: Invalid ticket ID
 * - 403: API key not authorized for subticket operations
 * - 404: Ticket not found
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

// Parse path info to get child ticket ID
// URL: /api/tickets-subtickets-parent.php/200.json -> path_info = /200.json
$path_info = Osticket::get_path_info();

// Extract ticket ID and format
// Pattern: /{ticket_id}.{format}
if (!preg_match('#^/(?P<id>\d+)\.(?P<format>json|xml)$#', $path_info, $matches)) {
    Http::response(400, 'Invalid URL format. Expected: /api/tickets-subtickets-parent.php/{child_ticket_id}.json', 'text/plain');
    exit;
}

$childId = (int)$matches['id'];
$format = $matches['format'];

// Only accept GET method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    Http::response(405, 'Method Not Allowed. Use GET', 'text/plain');
    exit;
}

// Create controller and get parent ticket
try {
    $controller = new SubticketApiController(null);
    $result = $controller->getParent($childId);

    // Return success with parent data
    if ($format === 'json') {
        Http::response(200, json_encode($result), 'application/json');
    } else {
        // XML format
        $xml = arrayToXml($result, 'response');
        Http::response(200, $xml, 'application/xml');
    }

} catch (Exception $e) {
    // Handle errors
    $code = $e->getCode() ?: 400;
    Http::response($code, $e->getMessage(), 'text/plain');
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
            // Secure XML encoding to prevent injection attacks
            $safeValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml->addChild($key, $safeValue);
        }
    }
}
