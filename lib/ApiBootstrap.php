<?php
/**
 * API Bootstrap Helper
 *
 * Centralizes common boilerplate code shared across all API endpoint files.
 * Reduces code duplication and ensures consistent error handling.
 *
 * Usage:
 *   require_once __DIR__ . '/../lib/ApiBootstrap.php';
 *   $bootstrap = ApiBootstrap::initialize();
 *
 *   // Parse path info
 *   $params = $bootstrap->parsePathInfo('#^/(?P<number>[^/.]+)\.(?P<format>json|xml)$#');
 *
 *   // Get controller
 *   $controller = $bootstrap->getController();
 *
 *   // Execute with error handling
 *   $bootstrap->execute(function() use ($controller, $params) {
 *       return $controller->getTicket($params['number']);
 *   });
 */

declare(strict_types=1);

class ApiBootstrap
{
    private const PLUGIN_PATH = 'plugins/api-endpoints/';
    private const MAX_JSON_DEPTH = 512;

    private string $format = 'json';
    private ?array $pathParams = null;

    /**
     * Initialize API bootstrap
     *
     * Performs common initialization:
     * - Loads osTicket bootstrap (if not already loaded)
     * - Validates INCLUDE_DIR
     * - Loads required classes
     *
     * IMPORTANT: The calling API file MUST load main.inc.php BEFORE calling this method!
     * This is because main.inc.php must be loaded relative to the API file location,
     * not relative to this bootstrap file.
     *
     * @return self
     * @throws RuntimeException If initialization fails
     */
    public static function initialize(): self
    {
        // INCLUDE_DIR should already be defined by main.inc.php loaded in the API file
        // If not defined, the API file didn't load main.inc.php correctly

        if (!defined('INCLUDE_DIR')) {
            self::sendErrorResponse(500, 'Fatal Error: Cannot access API outside of osTicket');
        }

        // Load core classes
        require_once INCLUDE_DIR . 'class.api.php';
        require_once INCLUDE_DIR . 'class.ticket.php';

        // Load plugin classes
        $pluginPath = INCLUDE_DIR . self::PLUGIN_PATH;

        if (!file_exists($pluginPath . 'controllers/ExtendedTicketApiController.php')) {
            self::sendErrorResponse(500, 'API Endpoints Plugin not properly installed');
        }

        require_once $pluginPath . 'controllers/ExtendedTicketApiController.php';

        if (file_exists($pluginPath . 'lib/XmlHelper.php')) {
            require_once $pluginPath . 'lib/XmlHelper.php';
        }

        return new self();
    }

    /**
     * Parse path info using regex pattern
     *
     * @param string $pattern Regex pattern with named groups
     * @param string|null $errorMessage Custom error message for invalid URL
     * @return array<string, string> Matched parameters
     * @throws InvalidArgumentException If path doesn't match pattern
     */
    public function parsePathInfo(string $pattern, ?string $errorMessage = null): array
    {
        $pathInfo = Osticket::get_path_info();

        if (!preg_match($pattern, $pathInfo, $matches)) {
            $message = $errorMessage ?? 'Invalid URL format';
            self::sendErrorResponse(400, $message);
        }

        // Extract format if present
        if (isset($matches['format'])) {
            $this->format = $matches['format'];
        }

        // Store and return named parameters only
        $this->pathParams = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        return $this->pathParams;
    }

    /**
     * Validate HTTP method
     *
     * @param string|array<string> $allowedMethods Single method or array of methods
     * @return string The actual request method
     */
    public function requireMethod(string|array $allowedMethods): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $allowed = is_array($allowedMethods) ? $allowedMethods : [$allowedMethods];

        if (!in_array($method, $allowed, true)) {
            $methodList = implode(', ', $allowed);
            self::sendErrorResponse(405, "Method Not Allowed. Use {$methodList}");
        }

        return $method;
    }

    /**
     * Parse JSON body from request
     *
     * @param bool $required Whether body is required
     * @return array<string, mixed> Parsed JSON data
     */
    public function parseJsonBody(bool $required = true): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Check for JSON content type
        if (str_contains($contentType, 'application/json')) {
            $body = file_get_contents('php://input');

            try {
                $data = json_decode($body, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);

                if (empty($data) && $required) {
                    self::sendErrorResponse(400, 'No data provided in JSON body');
                }

                return $data ?? [];
            } catch (JsonException $e) {
                self::sendErrorResponse(400, 'Invalid JSON: ' . $e->getMessage());
            }
        }

        // Fallback to POST data
        if (!empty($_POST)) {
            return $_POST;
        }

        if ($required) {
            self::sendErrorResponse(400, 'No data provided');
        }

        return [];
    }

    /**
     * Parse query parameters for GET requests
     *
     * @param array<string, mixed> $defaults Default values for parameters
     * @return array<string, mixed> Query parameters with defaults applied
     */
    public function parseQueryParams(array $defaults = []): array
    {
        $params = [];

        foreach ($_GET as $key => $value) {
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }

            // Trim string values
            $params[$key] = is_string($value) ? trim($value) : $value;
        }

        return array_merge($defaults, $params);
    }

    /**
     * Get the response format (json or xml)
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Create controller instance
     *
     * @return ExtendedTicketApiController
     */
    public function getController(): ExtendedTicketApiController
    {
        return new ExtendedTicketApiController(null);
    }

    /**
     * Create subticket controller instance
     *
     * @return SubticketApiController
     */
    public function getSubticketController(): SubticketApiController
    {
        $pluginPath = INCLUDE_DIR . self::PLUGIN_PATH;
        require_once $pluginPath . 'controllers/SubticketApiController.php';

        return new SubticketApiController(null);
    }

    /**
     * Execute callback with standardized error handling
     *
     * @param callable $callback Function to execute
     * @return void
     */
    public function execute(callable $callback): void
    {
        try {
            $result = $callback();
            $this->sendSuccessResponse($result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Send success response
     *
     * @param mixed $data Response data (array for JSON/XML, string for plain text)
     * @param int $code HTTP status code
     * @return never
     */
    public function sendSuccessResponse(mixed $data, int $code = 200): never
    {
        if (is_array($data)) {
            if ($this->format === 'xml' && class_exists('XmlHelper')) {
                $xml = XmlHelper::arrayToXml($data, 'response');
                Http::response($code, $xml, 'application/xml');
            } else {
                Http::response($code, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json');
            }
        } else {
            Http::response($code, (string)$data, 'text/plain');
        }

        exit;
    }

    /**
     * Handle exception and send appropriate error response
     *
     * @param Exception $e The exception to handle
     * @return never
     */
    public function handleException(Exception $e): never
    {
        $code = $e->getCode();

        // Validate HTTP status code range
        if ($code < 100 || $code > 599) {
            $code = 400;
        }

        self::sendErrorResponse($code, $e->getMessage());
    }

    /**
     * Send error response and exit
     *
     * @param int $code HTTP status code
     * @param string $message Error message
     * @return never
     */
    public static function sendErrorResponse(int $code, string $message): never
    {
        Http::response($code, $message, 'text/plain');
        exit;
    }
}
