<?php

namespace AtomFramework\Helpers;

/**
 * Helper for accessing external service configuration.
 */
class ExternalServiceHelper
{
    private static ?array $config = null;

    /**
     * Load configuration from file.
     */
    private static function loadConfig(): array
    {
        if (self::$config === null) {
            $configPath = dirname(__DIR__, 2) . '/config/external_services.php';
            self::$config = file_exists($configPath) ? require $configPath : [];
        }
        return self::$config;
    }

    /**
     * Get configuration value by dot notation path.
     */
    public static function get(string $path, $default = null)
    {
        $config = self::loadConfig();
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (!is_array($config) || !array_key_exists($key, $config)) {
                return $default;
            }
            $config = $config[$key];
        }

        return $config;
    }

    /**
     * Get Getty SPARQL endpoint.
     */
    public static function getGettySparqlEndpoint(): string
    {
        return self::get('getty.sparql_endpoint', 'http://vocab.getty.edu/sparql');
    }

    /**
     * Get Getty namespace by type.
     */
    public static function getGettyNamespace(string $type): string
    {
        return self::get("getty.namespaces.{$type}", '');
    }

    /**
     * Get CDN URL for a library.
     */
    public static function getCdnUrl(string $library): string
    {
        return self::get("cdn.{$library}", '');
    }

    /**
     * Get ontology namespace.
     */
    public static function getOntology(string $name): string
    {
        return self::get("ontologies.{$name}", '');
    }

    /**
     * Get rights statement base URL.
     */
    public static function getRightsStatementUrl(string $type = 'rightsstatements_org'): string
    {
        return self::get("rights.{$type}", '');
    }

    /**
     * Get payment gateway URL.
     */
    public static function getPaymentUrl(string $gateway, bool $sandbox = false): string
    {
        $mode = $sandbox ? 'sandbox_url' : 'live_url';
        return self::get("payment.{$gateway}.{$mode}", '');
    }

    /**
     * Get all namespaces for SPARQL prefixes.
     */
    public static function getSparqlPrefixes(): string
    {
        $prefixes = [
            'gvp' => 'http://vocab.getty.edu/ontology#',
            'skos' => self::getOntology('skos'),
            'xl' => 'http://www.w3.org/2008/05/skos-xl#',
            'foaf' => self::getOntology('foaf'),
            'schema' => self::getOntology('schema_org'),
            'dcterms' => self::getOntology('dcterms'),
        ];

        $lines = [];
        foreach ($prefixes as $prefix => $uri) {
            if ($uri) {
                $lines[] = "PREFIX {$prefix}: <{$uri}>";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Reload configuration (useful for testing).
     */
    public static function reload(): void
    {
        self::$config = null;
    }
}
