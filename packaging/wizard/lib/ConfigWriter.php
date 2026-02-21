<?php
/**
 * Write settings to ahg_settings table and config files
 */
class ConfigWriter
{
    private ?PDO $pdo = null;

    public function __construct()
    {
        $this->connectDB();
    }

    /**
     * Write a setting to ahg_settings table
     */
    public function set(string $key, string $value, string $group = 'general'): bool
    {
        if (!$this->pdo) return false;

        try {
            // Check if table exists
            $this->pdo->query('SELECT 1 FROM ahg_settings LIMIT 1');
        } catch (\Exception $e) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ahg_settings (setting_key, setting_value, setting_group, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
            );
            $stmt->execute([$key, $value, $group]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Batch write settings
     */
    public function setMultiple(array $settings, string $group = 'general'): int
    {
        $count = 0;
        foreach ($settings as $key => $value) {
            if ($this->set($key, (string)$value, $group)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get a setting value
     */
    public function get(string $key, string $default = ''): string
    {
        if (!$this->pdo) return $default;

        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM ahg_settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false) ? $val : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Get all settings in a group
     */
    public function getGroup(string $group): array
    {
        if (!$this->pdo) return [];

        try {
            $stmt = $this->pdo->prepare('SELECT setting_key, setting_value FROM ahg_settings WHERE setting_group = ?');
            $stmt->execute([$group]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function connectDB(): void
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

        $host = $conf['DB_HOST'] ?? 'localhost';
        $name = $conf['DB_NAME'] ?? 'atom';
        $user = $conf['DB_USER'] ?? 'atom';
        $pass = $conf['DB_PASS'] ?? '';

        try {
            $this->pdo = new PDO("mysql:host={$host};dbname={$name}", $user, $pass, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Exception $e) {
            $this->pdo = null;
        }
    }
}
