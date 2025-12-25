<?php

namespace AtomExtensions\Services;

class ConfigReader
{
    private array $dbConfig = [];
    private string $atomRoot;

    public function __construct()
    {
        $this->atomRoot = \sfConfig::get('sf_root_dir', dirname(__DIR__, 3));
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        // Try databases.yml first
        $ymlFile = $this->atomRoot . '/config/databases.yml';
        if (file_exists($ymlFile)) {
            $content = file_get_contents($ymlFile);
            
            if (preg_match('/dsn:\s*["\']?mysql:dbname=([^;]+);host=([^;\'"\s]+)/i', $content, $matches)) {
                $this->dbConfig['database'] = $matches[1];
                $this->dbConfig['host'] = $matches[2];
            }
            if (preg_match('/username:\s*["\']?([^\s\'"]+)/i', $content, $matches)) {
                $this->dbConfig['username'] = $matches[1];
            }
            if (preg_match('/password:\s*["\']?([^\s\'"]*)/i', $content, $matches)) {
                $this->dbConfig['password'] = trim($matches[1], "\"'");
            }
        }
        
        // Defaults
        $this->dbConfig['host'] = $this->dbConfig['host'] ?? 'localhost';
        $this->dbConfig['database'] = $this->dbConfig['database'] ?? 'archive';
        $this->dbConfig['username'] = $this->dbConfig['username'] ?? 'root';
        $this->dbConfig['password'] = $this->dbConfig['password'] ?? '';
        $this->dbConfig['port'] = $this->dbConfig['port'] ?? 3306;
    }

    public function getHost(): string { return $this->dbConfig['host']; }
    public function getDatabase(): string { return $this->dbConfig['database']; }
    public function getUsername(): string { return $this->dbConfig['username']; }
    public function getPassword(): string { return $this->dbConfig['password']; }
    public function getPort(): int { return (int)$this->dbConfig['port']; }
    public function getAtomRoot(): string { return $this->atomRoot; }
    public function getDatabaseConfig(): array { return $this->dbConfig; }
}
