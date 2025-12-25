<?php
declare(strict_types=1);
namespace AtomExtensions\Services;

/**
 * Cache Service - Replaces QubitCache (38 uses)
 */
class CacheService
{
    private static ?self $instance = null;
    private string $cacheDir;
    private array $memory = [];

    private function __construct()
    {
        $this->cacheDir = defined('SF_CACHE_DIR') ? SF_CACHE_DIR : '/tmp/atom_cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function get(string $key): mixed
    {
        if (isset($this->memory[$key])) {
            return $this->memory[$key]['value'];
        }
        
        $file = $this->getFile($key);
        if (file_exists($file)) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] > time()) {
                $this->memory[$key] = $data;
                return $data['value'];
            }
            @unlink($file);
        }
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $data = ['value' => $value, 'expires' => time() + $ttl];
        $this->memory[$key] = $data;
        return file_put_contents($this->getFile($key), serialize($data)) !== false;
    }

    public function remove(string $key): bool
    {
        unset($this->memory[$key]);
        $file = $this->getFile($key);
        return !file_exists($file) || @unlink($file);
    }

    public function removePattern(string $pattern): int
    {
        $count = 0;
        $regex = '/^' . str_replace(['*', ':'], ['.*', '\:'], $pattern) . '$/';
        
        foreach (array_keys($this->memory) as $key) {
            if (preg_match($regex, $key)) {
                unset($this->memory[$key]);
                $count++;
            }
        }
        
        foreach (glob($this->cacheDir . '/*.cache') as $file) {
            @unlink($file);
            $count++;
        }
        return $count;
    }

    public function clear(): bool
    {
        $this->memory = [];
        array_map('unlink', glob($this->cacheDir . '/*.cache') ?: []);
        return true;
    }

    private function getFile(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    /**
     * Get label for a term/object by ID (replaces QubitCache::getLabel).
     */
    public static function getLabel(int $id, string $class = 'QubitTerm'): ?string
    {
        $cacheKey = "label:{$class}:{$id}";
        $instance = self::getInstance();
        
        $cached = $instance->get($cacheKey);
        if (null !== $cached) {
            return $cached;
        }
        
        // Fetch from database
        $culture = 'en';
        if (class_exists('sfContext') && \sfContext::hasInstance()) {
            $culture = \sfContext::getInstance()->getUser()->getCulture();
        }
        
        $label = null;
        
        switch ($class) {
            case 'QubitTerm':
                $result = \Illuminate\Database\Capsule\Manager::table('term_i18n')
                    ->where('id', $id)
                    ->where('culture', $culture)
                    ->value('name');
                $label = $result ?: null;
                break;
                
            case 'QubitRepository':
                $result = \Illuminate\Database\Capsule\Manager::table('repository_i18n')
                    ->where('id', $id)
                    ->where('culture', $culture)
                    ->value('authorized_form_of_name');
                $label = $result ?: null;
                break;
                
            default:
                // Generic object lookup
                $table = strtolower(str_replace('Qubit', '', $class)) . '_i18n';
                try {
                    $result = \Illuminate\Database\Capsule\Manager::table($table)
                        ->where('id', $id)
                        ->where('culture', $culture)
                        ->value('name');
                    $label = $result ?: null;
                } catch (\Exception $e) {
                    $label = null;
                }
        }
        
        if (null !== $label) {
            $instance->set($cacheKey, $label, 3600);
        }
        
        return $label;
    }

    /**
     * Check if a key exists in cache.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
