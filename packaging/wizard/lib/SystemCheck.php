<?php
/**
 * Service health checks for the wizard dashboard
 */
class SystemCheck
{
    public static function getAll(): array
    {
        return [
            'php' => self::checkPHP(),
            'mysql' => self::checkMySQL(),
            'elasticsearch' => self::checkElasticsearch(),
            'nginx' => self::checkService('nginx'),
            'gearman' => self::checkService('gearman-job-server'),
            'memcached' => self::checkService('memcached'),
            'disk' => self::checkDisk(),
            'memory' => self::checkMemory(),
            'atom' => self::checkAtoM(),
            'heratio' => self::checkHeratio(),
        ];
    }

    public static function checkPHP(): array
    {
        return [
            'name' => 'PHP',
            'status' => 'ok',
            'version' => PHP_VERSION,
            'extensions' => [
                'mysql' => extension_loaded('mysqli') || extension_loaded('pdo_mysql'),
                'xml' => extension_loaded('xml'),
                'mbstring' => extension_loaded('mbstring'),
                'curl' => extension_loaded('curl'),
                'gd' => extension_loaded('gd'),
                'zip' => extension_loaded('zip'),
                'intl' => extension_loaded('intl'),
                'xsl' => extension_loaded('xsl'),
                'opcache' => extension_loaded('Zend OPcache'),
            ],
        ];
    }

    public static function checkMySQL(): array
    {
        $conf = self::loadConfig();
        $host = $conf['DB_HOST'] ?? 'localhost';
        $name = $conf['DB_NAME'] ?? 'atom';
        $user = $conf['DB_USER'] ?? 'atom';
        $pass = $conf['DB_PASS'] ?? '';

        try {
            $pdo = new PDO("mysql:host={$host};dbname={$name}", $user, $pass, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
            return ['name' => 'MySQL', 'status' => 'ok', 'version' => $ver];
        } catch (\Exception $e) {
            return ['name' => 'MySQL', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function checkElasticsearch(): array
    {
        $conf = self::loadConfig();
        $host = $conf['ES_HOST'] ?? 'localhost:9200';
        if (strpos($host, '://') === false) {
            $host = "http://{$host}";
        }

        $ch = curl_init("{$host}/_cluster/health");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $resp) {
            $data = json_decode($resp, true);
            return [
                'name' => 'Elasticsearch',
                'status' => 'ok',
                'cluster_status' => $data['status'] ?? 'unknown',
            ];
        }

        return ['name' => 'Elasticsearch', 'status' => 'error', 'message' => 'Not reachable'];
    }

    public static function checkService(string $name): array
    {
        exec("systemctl is-active {$name} 2>/dev/null", $output, $ret);
        $state = trim($output[0] ?? 'unknown');
        return [
            'name' => $name,
            'status' => ($state === 'active') ? 'ok' : 'warning',
            'state' => $state,
        ];
    }

    public static function checkDisk(): array
    {
        $conf = self::loadConfig();
        $path = $conf['ATOM_PATH'] ?? '/usr/share/nginx/atom';
        $free = disk_free_space($path ?: '/usr/share') ?: 0;
        $total = disk_total_space($path ?: '/usr/share') ?: 1;
        return [
            'name' => 'Disk',
            'status' => ($free > 1073741824) ? 'ok' : 'warning', // 1GB
            'free_gb' => round($free / 1073741824, 1),
            'total_gb' => round($total / 1073741824, 1),
        ];
    }

    public static function checkMemory(): array
    {
        $meminfo = @file_get_contents('/proc/meminfo');
        $total = 0;
        $avail = 0;
        if ($meminfo) {
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m)) {
                $total = (int)$m[1] / 1024; // MB
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m)) {
                $avail = (int)$m[1] / 1024;
            }
        }
        return [
            'name' => 'Memory',
            'status' => ($avail > 512) ? 'ok' : 'warning',
            'available_mb' => round($avail),
            'total_mb' => round($total),
        ];
    }

    public static function checkAtoM(): array
    {
        $conf = self::loadConfig();
        $path = $conf['ATOM_PATH'] ?? '/usr/share/nginx/atom';
        if (file_exists("{$path}/symfony")) {
            return ['name' => 'AtoM', 'status' => 'ok', 'path' => $path];
        }
        return ['name' => 'AtoM', 'status' => 'error', 'message' => 'Not found'];
    }

    public static function checkHeratio(): array
    {
        $conf = self::loadConfig();
        $path = $conf['ATOM_PATH'] ?? '/usr/share/nginx/atom';
        $fwPath = "{$path}/atom-framework/version.json";
        if (file_exists($fwPath)) {
            $ver = json_decode(file_get_contents($fwPath), true);
            return [
                'name' => 'Heratio Framework',
                'status' => 'ok',
                'version' => $ver['version'] ?? 'unknown',
            ];
        }
        return ['name' => 'Heratio Framework', 'status' => 'warning', 'message' => 'Not installed'];
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
