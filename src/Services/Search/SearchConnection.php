<?php

declare(strict_types=1);

namespace AtomFramework\Services\Search;

/**
 * Where the search engine is and how to reach it, for every caller that talks
 * HTTP to Elasticsearch or OpenSearch directly (catalog #316).
 *
 * These callers used to build "http://host:port" themselves, with no
 * credentials. Elasticsearch 8 turns on TLS and authentication by default, so
 * each would fail against a default install. They now take the address and the
 * connection options from here.
 *
 * Settings (config/app.yml, all optional; with none set nothing changes):
 *   opensearch_host / opensearch_port      host and port (the existing keys)
 *   elasticsearch_host / elasticsearch_port  older keys, used when the above are not set
 *   search_host_url                         a full base URL, used when no host is set
 *   search_scheme                           http (default) or https
 *   search_username / search_password       HTTP basic authentication
 *   search_ca_file                          CA certificate for an https engine
 *   search_verify_tls                       false to skip certificate checks (test only)
 */
final class SearchConnection
{
    /** Base URL with no trailing slash, e.g. https://localhost:9200 */
    public static function baseUrl(): string
    {
        $host = (string) (self::cfg('app_opensearch_host') ?: self::cfg('app_elasticsearch_host') ?: '');
        if ('' === $host) {
            $url = (string) self::cfg('app_search_host_url', '');
            if ('' !== $url) {
                return rtrim($url, '/');
            }
            $host = getenv('ES_HOST') ?: 'localhost';
        }
        $port = (int) (self::cfg('app_opensearch_port') ?: self::cfg('app_elasticsearch_port') ?: (getenv('ES_PORT') ?: 9200));
        $scheme = 'https' === strtolower((string) self::cfg('app_search_scheme', 'http')) ? 'https' : 'http';

        return $scheme.'://'.$host.':'.$port;
    }

    /** Full URL for a path such as "/myindex/_search" or "_bulk". */
    public static function url(string $path): string
    {
        return self::baseUrl().'/'.ltrim($path, '/');
    }

    /**
     * Credentials and TLS options to add to a curl handle. Empty when nothing is
     * configured, so a site that sets nothing behaves exactly as before.
     */
    public static function curlOptions(): array
    {
        $opts = [];
        $user = (string) self::cfg('app_search_username', '');
        if ('' !== $user) {
            $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $opts[CURLOPT_USERPWD] = $user.':'.(string) self::cfg('app_search_password', '');
        }
        $ca = (string) self::cfg('app_search_ca_file', '');
        if ('' !== $ca) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        if (in_array(self::cfg('app_search_verify_tls', true), [false, 'false', '0', 0], true)) {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        return $opts;
    }

    private static function cfg(string $key, $default = null)
    {
        return class_exists('\sfConfig', false) ? \sfConfig::get($key, $default) : $default;
    }
}
