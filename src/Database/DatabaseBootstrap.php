<?php

declare(strict_types=1);

namespace AtomExtensions\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Database bootstrap for Laravel Query Builder.
 * Replaces Propel Criteria with modern, fluent query interface.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class DatabaseBootstrap
{
    private static ?Capsule $capsule = null;

    /**
     * Initialize Laravel Query Builder using AtoM's database credentials.
     */
    public static function initialize(array $config): void
    {
        if (self::$capsule !== null) {
            return; // Already initialized
        }

        self::$capsule = new Capsule();

        self::$capsule->addConnection([
            'driver' => 'mysql',
            'host' => self::extractHost($config['dsn']),
            'port' => self::extractPort($config['dsn']),
            'database' => self::extractDatabase($config['dsn']),
            'username' => $config['username'],
            'password' => $config['password'],
            'charset' => $config['encoding'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false, // AtoM compatibility
            'engine' => null,
        ]);

        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();
    }

    /**
     * Initialize from AtoM's databases.yml configuration.
     */
    public static function initializeFromAtom(string $environment = 'all'): void
    {
        // Check if sfConfig is available (Symfony loaded)
        if (!class_exists('sfConfig')) {
            throw new \RuntimeException('Symfony not loaded yet - cannot access sfConfig');
        }

        // Load AtoM's database configuration
        $configPath = \sfConfig::get('sf_config_dir') . '/config.php';

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Database configuration not found at: {$configPath}");
        }

        $atomConfig = require $configPath;

        if (!isset($atomConfig[$environment]['propel']['param'])) {
            throw new \RuntimeException("Database configuration not found for environment: {$environment}");
        }

        $config = $atomConfig[$environment]['propel']['param'];

        self::initialize($config);
    }

    /**
     * Get the Capsule instance.
     */
    public static function getCapsule(): ?Capsule
    {
        return self::$capsule;
    }

    /**
     * Extract database name from DSN.
     */
    private static function extractDatabase(string $dsn): string
    {
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            return $matches[1];
        }

        throw new \InvalidArgumentException('Could not extract database name from DSN');
    }

    /**
     * Extract host from DSN.
     */
    private static function extractHost(string $dsn): string
    {
        if (preg_match('/host=([^;]+)/', $dsn, $matches)) {
            return $matches[1];
        }

        return 'localhost'; // Default if not specified
    }

    /**
     * Extract port from DSN.
     */
    private static function extractPort(string $dsn): int
    {
        if (preg_match('/port=([^;]+)/', $dsn, $matches)) {
            return (int) $matches[1];
        }

        return 3306; // Default MySQL port
    }

    /**
     * Test database connection.
     */
    public static function testConnection(): bool
    {
        try {
            if (self::$capsule === null) {
                return false;
            }

            self::$capsule->getConnection()->getPdo();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get connection info for debugging.
     */
    public static function getConnectionInfo(): array
    {
        if (self::$capsule === null) {
            return ['status' => 'not_initialized'];
        }

        $config = self::$capsule->getConnection()->getConfig();

        return [
            'status' => 'initialized',
            'driver' => $config['driver'],
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'charset' => $config['charset'],
        ];
    }
}
