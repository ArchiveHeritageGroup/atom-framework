<?php

namespace AtomFramework\Services;

/**
 * XML Parser Service — safe XML parsing with XXE protection.
 *
 * All XML parsing in the framework SHOULD use this service to prevent
 * XML External Entity (XXE) injection attacks. The service disables
 * external entity loading and network access in the XML parser.
 */
class XmlParserService
{
    /**
     * Safe flags for libxml: disable network access and CDATA sections.
     */
    private const SAFE_FLAGS = LIBXML_NONET | LIBXML_NOCDATA;

    /**
     * Parse an XML string using SimpleXML with XXE protection.
     *
     * @param string      $xml        The XML string to parse
     * @param string|null $className  Optional SimpleXMLElement subclass
     * @param int         $extraFlags Additional libxml flags to OR with safe defaults
     * @return \SimpleXMLElement|false The parsed document, or false on failure
     */
    public static function parseString(string $xml, ?string $className = null, int $extraFlags = 0): \SimpleXMLElement|false
    {
        $flags = self::SAFE_FLAGS | $extraFlags;
        $className = $className ?? \SimpleXMLElement::class;

        // Clear any previous libxml errors
        libxml_clear_errors();
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $result = simplexml_load_string($xml, $className, $flags);

            if ($result === false) {
                $errors = libxml_get_errors();
                if (!empty($errors)) {
                    $errorMsg = self::formatLibxmlErrors($errors);
                    error_log('XmlParserService::parseString failed: ' . $errorMsg);
                }
                return false;
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    /**
     * Parse an XML file using SimpleXML with XXE protection.
     *
     * @param string      $filepath   Path to the XML file
     * @param string|null $className  Optional SimpleXMLElement subclass
     * @param int         $extraFlags Additional libxml flags
     * @return \SimpleXMLElement|false
     */
    public static function parseFile(string $filepath, ?string $className = null, int $extraFlags = 0): \SimpleXMLElement|false
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            error_log('XmlParserService::parseFile: file not found or not readable: ' . $filepath);
            return false;
        }

        $xml = file_get_contents($filepath);
        if ($xml === false) {
            return false;
        }

        return self::parseString($xml, $className, $extraFlags);
    }

    /**
     * Load an XML string into a DOMDocument with XXE protection.
     *
     * @param string $xml        The XML string
     * @param int    $extraFlags Additional libxml flags
     * @return \DOMDocument|null The parsed document, or null on failure
     */
    public static function loadDom(string $xml, int $extraFlags = 0): ?\DOMDocument
    {
        $flags = self::SAFE_FLAGS | $extraFlags;

        libxml_clear_errors();
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument();
            $dom->substituteEntities = false;

            $result = $dom->loadXML($xml, $flags);

            if (!$result) {
                $errors = libxml_get_errors();
                if (!empty($errors)) {
                    $errorMsg = self::formatLibxmlErrors($errors);
                    error_log('XmlParserService::loadDom failed: ' . $errorMsg);
                }
                return null;
            }

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    /**
     * Load an XML file into a DOMDocument with XXE protection.
     */
    public static function loadDomFile(string $filepath, int $extraFlags = 0): ?\DOMDocument
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            error_log('XmlParserService::loadDomFile: file not found or not readable: ' . $filepath);
            return null;
        }

        $xml = file_get_contents($filepath);
        if ($xml === false) {
            return null;
        }

        return self::loadDom($xml, $extraFlags);
    }

    /**
     * Format libxml errors into a readable string.
     *
     * @param \LibXMLError[] $errors
     */
    private static function formatLibxmlErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            $level = match ($error->level) {
                LIBXML_ERR_WARNING => 'Warning',
                LIBXML_ERR_ERROR => 'Error',
                LIBXML_ERR_FATAL => 'Fatal',
                default => 'Unknown',
            };
            $messages[] = sprintf('[%s] Line %d: %s', $level, $error->line, trim($error->message));
        }
        return implode('; ', $messages);
    }
}
