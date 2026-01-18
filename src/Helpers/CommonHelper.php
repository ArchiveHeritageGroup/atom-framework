<?php

namespace AtomFramework\Helpers;

/**
 * Common utility functions used across AHG plugins.
 * Consolidates frequently repeated code patterns.
 */
class CommonHelper
{
    /**
     * Get current datetime in MySQL format.
     */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Get current date in MySQL format.
     */
    public static function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * Get date relative to today.
     */
    public static function dateAfter(int $days): string
    {
        return date('Y-m-d', strtotime("+{$days} days"));
    }

    /**
     * Get client IP address safely.
     */
    public static function getClientIp(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Get user agent safely (truncated).
     */
    public static function getUserAgent(int $maxLength = 500): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return $ua ? substr($ua, 0, $maxLength) : null;
    }

    /**
     * Generate a UUID v4.
     */
    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Get current user ID from Symfony context.
     */
    public static function getCurrentUserId(): ?int
    {
        if (!class_exists('sfContext') || !\sfContext::hasInstance()) {
            return null;
        }

        $user = \sfContext::getInstance()->getUser();
        if (!$user || !$user->isAuthenticated()) {
            return null;
        }

        return $user->getAttribute('user_id');
    }

    /**
     * Get current username from Symfony context.
     */
    public static function getCurrentUsername(): ?string
    {
        if (!class_exists('sfContext') || !\sfContext::hasInstance()) {
            return null;
        }

        $user = \sfContext::getInstance()->getUser();
        if (!$user || !$user->isAuthenticated()) {
            return null;
        }

        return $user->getAttribute('username');
    }

    /**
     * Safely JSON encode with error handling.
     */
    public static function jsonEncode($data): ?string
    {
        if (empty($data)) {
            return null;
        }

        $json = json_encode($data);
        return $json !== false ? $json : null;
    }

    /**
     * Safely JSON decode with error handling.
     */
    public static function jsonDecode(?string $json, bool $assoc = true)
    {
        if (empty($json)) {
            return null;
        }

        $data = json_decode($json, $assoc);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    /**
     * Check if a value is empty or whitespace only.
     */
    public static function isBlank($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    /**
     * Get non-blank value or null.
     */
    public static function nullIfBlank($value)
    {
        return self::isBlank($value) ? null : $value;
    }

    /**
     * Build standard timestamps array for insert operations.
     */
    public static function timestamps(): array
    {
        $now = self::now();
        return [
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Build updated_at timestamp for update operations.
     */
    public static function touchTimestamp(): array
    {
        return [
            'updated_at' => self::now(),
        ];
    }

    /**
     * Safely get array value with default.
     */
    public static function get(array $array, string $key, $default = null)
    {
        return array_key_exists($key, $array) && !self::isBlank($array[$key])
            ? $array[$key]
            : $default;
    }

    /**
     * Format a reference number with prefix and sequence.
     */
    public static function formatReference(string $prefix, string $code, int $sequence, string $separator = '-'): string
    {
        $year = date('Y');
        $month = date('m');
        return sprintf('%s%s%s%s%s%s%04d', $prefix, $separator, $code, $separator, $year, $month, $sequence);
    }

    /**
     * Truncate string safely with ellipsis.
     */
    public static function truncate(string $string, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Escape HTML entities.
     */
    public static function escape(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Convert array keys to snake_case.
     */
    public static function snakeKeys(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $snakeKey = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($key)));
            $result[$snakeKey] = $value;
        }
        return $result;
    }

    /**
     * Filter array to only include non-null values.
     */
    public static function filterNonNull(array $array): array
    {
        return array_filter($array, fn($v) => $v !== null);
    }

    /**
     * Filter array to only include non-blank values.
     */
    public static function filterNonBlank(array $array): array
    {
        return array_filter($array, fn($v) => !self::isBlank($v));
    }
}
