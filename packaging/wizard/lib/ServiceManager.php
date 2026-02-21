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
     * Clear AtoM caches
     */
    public static function clearCaches(): bool
    {
        $conf = self::loadConfig();
        $atomPath = $conf['ATOM_PATH'] ?? '/usr/share/nginx/atom';

        // Clear cache directory
        $cacheDir = "{$atomPath}/cache";
        if (is_dir($cacheDir)) {
            array_map('unlink', glob("{$cacheDir}/*") ?: []);
        }

        // Run symfony cc
        exec("cd {$atomPath} && sudo -u www-data php symfony cc 2>/dev/null", $output, $ret);

        return $ret === 0;
    }

    /**
     * Restart a systemd service
     */
    public static function restartService(string $name): bool
    {
        exec("systemctl restart {$name} 2>/dev/null", $output, $ret);
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
