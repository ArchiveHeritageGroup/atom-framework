<?php

// Dont define if were in Symfony context - let core handle it
if (defined('SF_ROOT_DIR')) {
    return;
}

/**
 * QubitCache Compatibility Layer
 * 
 * Redirects QubitCache calls to CacheService.
 */
if (!class_exists('QubitCache', false)) {
    class QubitCache
    {
        private static ?self $instance = null;
        
        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }
        
        public function get(string $key): mixed
        {
            return \AtomExtensions\Services\CacheService::getInstance()->get($key);
        }
        
        public function set(string $key, mixed $value, int $ttl = 3600): bool
        {
            return \AtomExtensions\Services\CacheService::getInstance()->set($key, $value, $ttl);
        }
        
        public function remove(string $key): bool
        {
            return \AtomExtensions\Services\CacheService::getInstance()->remove($key);
        }
        
        public function removePattern(string $pattern): int
        {
            return \AtomExtensions\Services\CacheService::getInstance()->removePattern($pattern);
        }
        
        public function clear(): bool
        {
            return \AtomExtensions\Services\CacheService::getInstance()->clear();
        }
        
        public function has(string $key): bool
        {
            return $this->get($key) !== null;
        }
    }
}
