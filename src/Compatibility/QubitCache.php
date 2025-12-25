<?php

/**
 * QubitCache Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\CacheService directly
 */

use AtomExtensions\Services\CacheService;

class QubitCache
{
    private static ?CacheService $instance = null;

    public static function getInstance(): CacheService
    {
        if (self::$instance === null) {
            self::$instance = CacheService::getInstance();
        }
        return self::$instance;
    }
}
