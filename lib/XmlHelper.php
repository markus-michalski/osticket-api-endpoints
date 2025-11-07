<?php
/**
 * XML Helper Class
 *
 * Provides reusable XML conversion methods for API responses
 *
 * Usage:
 *   $xml = XmlHelper::arrayToXml($data, 'response');
 *   if ($xml !== false) {
 *       header('Content-Type: application/xml; charset=UTF-8');
 *       echo $xml;
 *   }
 */
class XmlHelper {

    /**
     * Convert array to XML string
     *
     * @param array $data Data to convert
     * @param string $rootElement Root element name
     * @return string|false XML string or false on error
     */
    public static function arrayToXml(array $data, string $rootElement = 'root') {
        try {
            $xml = new SimpleXMLElement("<$rootElement/>");
            self::arrayToXmlRecursive($data, $xml);
            return $xml->asXML();
        } catch (Exception $e) {
            error_log('XmlHelper::arrayToXml() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Recursive helper for array to XML conversion
     *
     * Handles nested arrays, numeric keys, and null values.
     * Escapes values to prevent XML injection.
     *
     * @param array $data Data to convert
     * @param SimpleXMLElement $xml XML element to populate
     * @return void
     */
    private static function arrayToXmlRecursive(array $data, SimpleXMLElement &$xml): void {
        foreach ($data as $key => $value) {
            // Handle numeric keys
            if (is_numeric($key)) {
                $key = 'item';
            }

            // Recursive handling for nested arrays
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                self::arrayToXmlRecursive($value, $subnode);
            } else {
                // Handle null values and escape for XML safety
                $safeValue = $value !== null
                    ? htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    : '';
                $xml->addChild($key, $safeValue);
            }
        }
    }
}
