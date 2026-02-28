<?php

namespace AtomFramework\Services;

/**
 * HTTP Client Service — safe outbound HTTP with SSRF protection.
 *
 * All outbound HTTP requests from the framework SHOULD use this service
 * to benefit from private IP blocking, DNS rebinding protection,
 * SSL verification, response size limits, and timeout enforcement.
 */
class HttpClientService
{
    /** Default timeout in seconds */
    private const DEFAULT_TIMEOUT = 15;

    /** Default connect timeout in seconds */
    private const DEFAULT_CONNECT_TIMEOUT = 10;

    /** Maximum response body size (10 MB) */
    private const MAX_RESPONSE_SIZE = 10 * 1024 * 1024;

    /** Maximum number of redirects */
    private const MAX_REDIRECTS = 5;

    /** Allowed URL schemes */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** Blocked hostnames (cloud metadata endpoints) */
    private const BLOCKED_HOSTS = [
        '169.254.169.254',
        'metadata.google.internal',
        'metadata.internal',
    ];

    /** User agent string */
    private const USER_AGENT = 'AtoM-Heratio/2.8 (Archive Management System)';

    /**
     * Perform a GET request.
     *
     * @param string $url     The URL to fetch
     * @param array  $headers Additional headers
     * @param array  $options Override options (timeout, maxSize, verifySsl)
     * @return array{status: int, body: string, headers: array, error: ?string}
     */
    public static function get(string $url, array $headers = [], array $options = []): array
    {
        return self::request('GET', $url, null, $headers, $options);
    }

    /**
     * Perform a POST request.
     *
     * @param string      $url     The URL to post to
     * @param string|null $body    Request body
     * @param array       $headers Additional headers
     * @param array       $options Override options
     * @return array{status: int, body: string, headers: array, error: ?string}
     */
    public static function post(string $url, ?string $body = null, array $headers = [], array $options = []): array
    {
        return self::request('POST', $url, $body, $headers, $options);
    }

    /**
     * Perform an HTTP request with SSRF protection.
     *
     * @param string      $method  HTTP method
     * @param string      $url     Target URL
     * @param string|null $body    Request body (for POST/PUT/PATCH)
     * @param array       $headers Additional headers
     * @param array       $options Override options
     * @return array{status: int, body: string, headers: array, error: ?string}
     */
    public static function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
        array $options = []
    ): array {
        $result = ['status' => 0, 'body' => '', 'headers' => [], 'error' => null];

        // Validate URL scheme
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            $result['error'] = 'Invalid URL';
            return $result;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            $result['error'] = 'URL scheme not allowed: ' . $scheme;
            return $result;
        }

        // Check blocked hosts
        $host = $parsed['host'];
        if (self::isBlockedHost($host)) {
            $result['error'] = 'Host is blocked (metadata endpoint)';
            return $result;
        }

        // DNS pre-resolution to prevent DNS rebinding
        $resolvedIps = gethostbynamel($host);
        if ($resolvedIps === false) {
            $result['error'] = 'DNS resolution failed for: ' . $host;
            return $result;
        }

        // Check all resolved IPs for private ranges
        foreach ($resolvedIps as $ip) {
            if (self::isPrivateIp($ip)) {
                $result['error'] = 'Target resolves to private IP: ' . $ip;
                return $result;
            }
        }

        // Build curl request
        $timeout = (int) ($options['timeout'] ?? self::DEFAULT_TIMEOUT);
        $maxSize = (int) ($options['maxSize'] ?? self::MAX_RESPONSE_SIZE);
        $verifySsl = $options['verifySsl'] ?? true;
        $followRedirects = $options['followRedirects'] ?? true;

        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $curlHeaders[] = $value; // Already formatted "Key: Value"
            } else {
                $curlHeaders[] = $key . ': ' . $value;
            }
        }

        // Track response size via progress callback
        $receivedSize = 0;

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => (int) ($options['connectTimeout'] ?? self::DEFAULT_CONNECT_TIMEOUT),
            CURLOPT_SSL_VERIFYPEER => (bool) $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_FOLLOWLOCATION => false, // Handle redirects manually for IP re-validation
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$responseHeaders) {
                $len = strlen($headerLine);
                $header = explode(':', $headerLine, 2);
                if (count($header) === 2) {
                    $responseHeaders[trim($header[0])] = trim($header[1]);
                }
                return $len;
            },
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) use ($maxSize, &$receivedSize) {
                $receivedSize = $dlNow;
                if ($dlNow > $maxSize) {
                    return 1; // Abort transfer
                }
                return 0;
            },
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        // Use first resolved IP to prevent DNS rebinding on actual connection
        if (!empty($resolvedIps)) {
            $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
            curl_setopt($ch, CURLOPT_RESOLVE, [$host . ':' . $port . ':' . $resolvedIps[0]]);
        }

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($responseBody === false || $errno !== 0) {
            $result['error'] = $error ?: 'curl error ' . $errno;
            $result['status'] = $httpCode;
            return $result;
        }

        // Handle redirects manually with IP re-validation
        if ($followRedirects && in_array($httpCode, [301, 302, 303, 307, 308], true)) {
            $location = $responseHeaders['Location'] ?? $responseHeaders['location'] ?? null;
            if ($location) {
                $redirectCount = (int) ($options['_redirectCount'] ?? 0);
                if ($redirectCount >= self::MAX_REDIRECTS) {
                    $result['error'] = 'Too many redirects';
                    $result['status'] = $httpCode;
                    return $result;
                }

                // Resolve relative URLs
                if (!preg_match('#^https?://#i', $location)) {
                    $base = $scheme . '://' . $host;
                    if (isset($parsed['port'])) {
                        $base .= ':' . $parsed['port'];
                    }
                    $location = $base . '/' . ltrim($location, '/');
                }

                $redirectOptions = $options;
                $redirectOptions['_redirectCount'] = $redirectCount + 1;

                // Re-validate the redirect target (SSRF protection on redirects)
                return self::request($method === 'POST' && in_array($httpCode, [301, 302, 303]) ? 'GET' : $method, $location, $body, $headers, $redirectOptions);
            }
        }

        $result['status'] = $httpCode;
        $result['body'] = $responseBody;
        $result['headers'] = $responseHeaders;

        return $result;
    }

    /**
     * Check if an IP address is in a private/reserved range.
     *
     * Blocks: RFC 1918, link-local, loopback, multicast, reserved
     *
     * @param string $ip The IP address to check
     * @return bool True if the IP is private/reserved
     */
    public static function isPrivateIp(string $ip): bool
    {
        // Check against blocked hosts list
        if (in_array($ip, self::BLOCKED_HOSTS, true)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Check if a hostname is in the blocked list.
     */
    public static function isBlockedHost(string $host): bool
    {
        $normalized = strtolower(trim($host));

        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($normalized === $blocked) {
                return true;
            }
        }

        return false;
    }
}
