<?php

namespace AtomFramework\Services;

use AtomFramework\Contracts\QueueJobInterface;

/**
 * Static registry mapping job_type strings to handler classes.
 *
 * Plugins register handlers in their initialize() method:
 *   QueueJobRegistry::register('ingest:commit', QueueCliTaskHandler::class);
 */
class QueueJobRegistry
{
    /** @var array<string, string> job_type => handler class name */
    private static array $handlers = [];

    /**
     * Register a handler class for a job type.
     *
     * @param string $jobType  The job type identifier (e.g. 'ingest:commit')
     * @param string $handlerClass Fully-qualified class implementing QueueJobInterface
     */
    public static function register(string $jobType, string $handlerClass): void
    {
        self::$handlers[$jobType] = $handlerClass;
    }

    /**
     * Resolve a job type to its handler instance.
     *
     * @return QueueJobInterface|null
     */
    public static function resolve(string $jobType): ?QueueJobInterface
    {
        if (!isset(self::$handlers[$jobType])) {
            return null;
        }

        $class = self::$handlers[$jobType];

        if (!class_exists($class)) {
            return null;
        }

        $instance = new $class();

        if (!$instance instanceof QueueJobInterface) {
            return null;
        }

        return $instance;
    }

    /**
     * Check if a handler is registered for a job type.
     */
    public static function has(string $jobType): bool
    {
        return isset(self::$handlers[$jobType]);
    }

    /**
     * Get all registered job types.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$handlers;
    }

    /**
     * Clear registry (for testing).
     */
    public static function clear(): void
    {
        self::$handlers = [];
    }
}
