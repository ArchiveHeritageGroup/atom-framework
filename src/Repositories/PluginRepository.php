<?php

declare(strict_types=1);

namespace Atom\Framework\Repositories;

use PDO;

class PluginRepository
{
    protected const TABLE_PLUGIN = 'atom_plugin';
    protected const TABLE_DEPENDENCY = 'atom_plugin_dependency';
    protected const TABLE_AUDIT = 'atom_plugin_audit';

    protected ?PDO $pdo = null;

    protected function getPdo(): PDO
    {
        if (null !== $this->pdo) {
            return $this->pdo;
        }

        if (class_exists('Propel')) {
            $connection = \Propel::getConnection();
            if ($connection instanceof PDO) {
                $this->pdo = $connection;
                return $this->pdo;
            }
            if (method_exists($connection, 'getWrappedConnection')) {
                $this->pdo = $connection->getWrappedConnection();
                return $this->pdo;
            }
        }

        $configPath = \sfConfig::get('sf_root_dir') . '/config/config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            if (isset($config['all']['propel']['param'])) {
                $params = $config['all']['propel']['param'];
                $dsn = $params['dsn'] ?? 'mysql:dbname=atom;host=localhost';
                if (strpos($dsn, 'host=') === false) {
                    $dsn = str_replace('mysql:', 'mysql:host=localhost;', $dsn);
                }
                $this->pdo = new PDO($dsn, $params['username'] ?? 'root', $params['password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                return $this->pdo;
            }
        }
        throw new \RuntimeException('Could not establish database connection');
    }

    public function findAll(array $filters = []): array
    {
        $sql = "SELECT * FROM " . self::TABLE_PLUGIN . " WHERE 1=1";
        $params = [];
        if (isset($filters['is_enabled'])) {
            $sql .= " AND is_enabled = ?";
            $params[] = $filters['is_enabled'] ? 1 : 0;
        }
        if (isset($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }
        if (isset($filters['is_core'])) {
            $sql .= " AND is_core = ?";
            $params[] = $filters['is_core'] ? 1 : 0;
        }
        $sql .= " ORDER BY load_order, name";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEnabled(): array
    {
        $sql = "SELECT name FROM " . self::TABLE_PLUGIN . " WHERE is_enabled = 1 ORDER BY load_order, name";
        $stmt = $this->getPdo()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findByName(string $name): ?array
    {
        $sql = "SELECT * FROM " . self::TABLE_PLUGIN . " WHERE name = ?";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function exists(string $name): bool
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE_PLUGIN . " WHERE name = ?";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$name]);
        return $stmt->fetchColumn() > 0;
    }

    public function isEnabled(string $name): bool
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE_PLUGIN . " WHERE name = ? AND is_enabled = 1";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$name]);
        return $stmt->fetchColumn() > 0;
    }

    public function enable(string $name): bool
    {
        $sql = "UPDATE " . self::TABLE_PLUGIN . " SET is_enabled = 1, enabled_at = ?, disabled_at = NULL, updated_at = ? WHERE name = ?";
        $now = date('Y-m-d H:i:s');
        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute([$now, $now, $name]);
    }

    public function disable(string $name): bool
    {
        $sql = "UPDATE " . self::TABLE_PLUGIN . " SET is_enabled = 0, disabled_at = ?, updated_at = ? WHERE name = ?";
        $now = date('Y-m-d H:i:s');
        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute([$now, $now, $name]);
    }

    public function getDependencies(int $pluginId): array
    {
        $sql = "SELECT * FROM " . self::TABLE_DEPENDENCY . " WHERE plugin_id = ?";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$pluginId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDependents(string $pluginName): array
    {
        $sql = "SELECT p.name, p.id FROM " . self::TABLE_DEPENDENCY . " d JOIN " . self::TABLE_PLUGIN . " p ON p.id = d.plugin_id WHERE d.requires_plugin = ? AND p.is_enabled = 1";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$pluginName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addAuditLog(array $data): int
    {
        $sql = "INSERT INTO " . self::TABLE_AUDIT . " (plugin_id, user_id, action, previous_state, new_state, reason, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([
            $data['plugin_id'],
            $data['user_id'] ?? null,
            $data['action'],
            isset($data['previous_state']) ? json_encode($data['previous_state']) : null,
            isset($data['new_state']) ? json_encode($data['new_state']) : null,
            $data['reason'] ?? null,
            $data['ip_address'] ?? null,
            date('Y-m-d H:i:s'),
        ]);
        return (int) $this->getPdo()->lastInsertId();
    }

    public function getAuditLog(?int $pluginId = null, int $limit = 50): array
    {
        $sql = "SELECT a.*, p.name as plugin_name FROM " . self::TABLE_AUDIT . " a JOIN " . self::TABLE_PLUGIN . " p ON p.id = a.plugin_id";
        $params = [];
        if (null !== $pluginId) {
            $sql .= " WHERE a.plugin_id = ?";
            $params[] = $pluginId;
        }
        $sql .= " ORDER BY a.created_at DESC LIMIT " . (int) $limit;
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllPluginNames(): array
    {
        $sql = "SELECT name FROM " . self::TABLE_PLUGIN;
        $stmt = $this->getPdo()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
