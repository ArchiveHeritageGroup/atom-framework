<?php

declare(strict_types=1);

namespace AtomExtensions\Helpers;

/**
 * Culture Helper - Provides current user culture for i18n queries.
 *
 * Replaces hardcoded 'en' culture references throughout the framework.
 */
class CultureHelper
{
    private static ?string $defaultCulture = 'en';
    private static ?string $overrideCulture = null;

    /**
     * Get the current user's culture.
     *
     * Priority:
     * 1. Override culture (for testing/API)
     * 2. sfContext user culture (logged in user)
     * 3. sfContext default culture
     * 4. Default 'en'
     */
    public static function getCulture(): string
    {
        // Check for override first (useful for API/testing)
        if (null !== self::$overrideCulture) {
            return self::$overrideCulture;
        }

        // Try to get from sfContext
        if (class_exists('sfContext') && \sfContext::hasInstance()) {
            try {
                $user = \sfContext::getInstance()->getUser();
                if ($user && method_exists($user, 'getCulture')) {
                    $culture = $user->getCulture();
                    if (!empty($culture)) {
                        return $culture;
                    }
                }
            } catch (\Exception $e) {
                // Fall through to default
            }
        }

        return self::$defaultCulture;
    }

    /**
     * Set override culture (for API/testing).
     */
    public static function setOverrideCulture(?string $culture): void
    {
        self::$overrideCulture = $culture;
    }

    /**
     * Clear override culture.
     */
    public static function clearOverride(): void
    {
        self::$overrideCulture = null;
    }

    /**
     * Set default culture.
     */
    public static function setDefaultCulture(string $culture): void
    {
        self::$defaultCulture = $culture;
    }

    /**
     * Get all available cultures from AtoM settings.
     */
    public static function getAvailableCultures(): array
    {
        // Default cultures
        $cultures = ['en', 'fr', 'es', 'pt', 'de', 'nl', 'it'];

        // Try to get from sfConfig
        if (function_exists('sfConfig') || class_exists('sfConfig')) {
            try {
                $configured = \sfConfig::get('app_i18n_cultures', []);
                if (!empty($configured)) {
                    return $configured;
                }
            } catch (\Exception $e) {
                // Fall through to default
            }
        }

        return $cultures;
    }

    /**
     * Check if a culture is valid/available.
     */
    public static function isValidCulture(string $culture): bool
    {
        return in_array($culture, self::getAvailableCultures(), true);
    }

    /**
     * Get culture for database queries with fallback.
     *
     * Use this in queries to handle culture fallback properly.
     */
    public static function getQueryCulture(?string $preferred = null): string
    {
        if (null !== $preferred && self::isValidCulture($preferred)) {
            return $preferred;
        }

        return self::getCulture();
    }
}
