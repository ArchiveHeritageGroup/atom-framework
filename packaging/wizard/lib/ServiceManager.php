<?php
/**
 * Service restart and cache management
 */
class ServiceManager
{
    /**
     * Restart PHP-FPM and Nginx
     */
    public static function restartWebServices(): array
    {
        $results = [];

        // Find PHP-FPM version
        $phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $fpmService = "php{$phpVer}-fpm";

        $results['php-fpm'] = self::restartService($fpmService);
        $results['nginx'] = self::restartService('nginx');

        return $results;
    }

    /**
     * Allowed service names for systemctl operations.
     */
    private const ALLOWED_SERVICES = [
        'nginx',
        'php8.3-fpm',
        'php8.2-fpm',
        'php8.1-fpm',
        'php8.0-fpm',
        'mysql',
        'mariadb',
        'elasticsearch',
        'memcached',
        'redis',
    ];

    /**
     * Clear AtoM caches
     */
    public static function clearCaches(): bool
    {
        $conf = self::loadConfig();
        $atomPath = $conf['ATOM_PATH'] ?? '/usr/share/nginx/atom';

        // Validate path exists
        $realPath = realpath($atomPath);
        if ($realPath === false || !is_dir($realPath)) {
            return false;
        }

        // Clear cache directory
        $cacheDir = $realPath . '/cache';
        if (is_dir($cacheDir)) {
            array_map('unlink', glob("{$cacheDir}/*") ?: []);
        }

        // Run symfony cc
        exec('cd ' . escapeshellarg($realPath) . ' && sudo -u www-data php symfony cc 2>/dev/null', $output, $ret);

        return $ret === 0;
    }

    /**
     * Restart a systemd service
     */
    public static function restartService(string $name): bool
    {
        if (!in_array($name, self::ALLOWED_SERVICES, true)) {
            return false;
        }

        exec('systemctl restart ' . escapeshellarg($name) . ' 2>/dev/null', $output, $ret);
        return $ret === 0;
    }

    private static function loadConfig(): array
    {
        $conf = [];
        $file = '/etc/atom-heratio/atom-heratio.conf';
        if (file_exists($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with($line, '#')) continue;
                if (strpos($line, '=') !== false) {
                    [$key, $val] = explode('=', $line, 2);
                    $conf[trim($key)] = trim(trim($val), '"\'');
                }
            }
        }
        return $conf;
    }
}
