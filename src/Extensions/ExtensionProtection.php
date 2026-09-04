<?php

declare(strict_types=1);

namespace AtomFramework\Extensions;

use PDO;
use PDOException;

/**
 * Extension Protection Service
 *
 * Handles protection rules for plugins:
 * - Core plugins cannot be disabled
 * - Locked plugins cannot be modified
 * - Plugins with records cannot be disabled without force
 *
 * Uses direct PDO connection (works in CLI and web context)
 */
class ExtensionProtection
{
    /**
     * Core plugins that cannot be disabled.
     */
    private const CORE_PLUGINS = [
        'ahgThemeB5Plugin',
        'ahgSecurityClearancePlugin',
    ];

    private ?PDO $pdo = null;

    /**
     * Get PDO connection.
     */
    private function getConnection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        // Direct PDO connection (Phase 5: Propel path removed)
        $configFile = $this->findConfigFile();
        if (!$configFile || !file_exists($configFile)) {
            throw new \RuntimeException('Config file not found');
        }

        $config = include $configFile;

        // Parse AtoM config structure: all -> propel -> param
        $dbConfig = $config['all']['propel']['param'] ?? [];

        if (empty($dbConfig['dsn'])) {
            throw new \RuntimeException('Database DSN not found in config');
        }

        $this->pdo = new PDO(
            $dbConfig['dsn'],
            $dbConfig['username'] ?? 'root',
            $dbConfig['password'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $this->pdo;
    }

    /**
     * Find the AtoM config file.
     */
    private function findConfigFile(): ?string
    {
        // Use PathResolver for consistent path detection
        if (class_exists(\AtomFramework\Helpers\PathResolver::class)) {
            $configFile = \AtomFramework\Helpers\PathResolver::getConfigFile();
            if (file_exists($configFile)) {
                return $configFile;
            }
        }

        // Check environment variable
        $atomRoot = getenv('ATOM_ROOT') ?: ($_SERVER['ATOM_ROOT'] ?? null);
        if ($atomRoot && file_exists($atomRoot . '/config/config.php')) {
            return $atomRoot . '/config/config.php';
        }

        // Try relative to this file
        $frameworkPath = dirname(__DIR__, 2);
        $atomPath = dirname($frameworkPath);
        $configPath = $atomPath . '/config/config.php';

        if (file_exists($configPath)) {
            return $configPath;
        }

        return null;
    }

    /**
     * Locate a plugin's directory, or null when it is not on this instance.
     *
     * $root is injectable so this can be tested without a real AtoM install. It
     * defaults to the detected root, so production behaviour is unchanged; only a
     * test passes it. Without the seam the only way to assert a POSITIVE result was
     * to depend on directories existing on the host, which passes on a live
     * instance and fails everywhere else - as it did on first run in CI.
     *
     * Three roots, because plugins legitimately live in all of them: `plugins/`
     * (base AtoM, and symlinks to the AHG set), `atom-ahg-plugins/` (the AHG
     * checkout itself), and `vendor/symfony/lib/plugins/` (Symfony's own, such as
     * sfPropelPlugin, which is enabled everywhere and lives nowhere else).
     * Checking only the first two reports sfPropelPlugin as missing.
     */
    public function findPluginDirectory(string $pluginName, ?string $root = null): ?string
    {
        // This value reaches filesystem paths and, in the web UI, arrives from a
        // request parameter. Plugin names are alphanumeric; anything else is
        // rejected rather than resolved.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $pluginName)) {
            return null;
        }

        $root ??= $this->getAtomRoot();
        if (null === $root) {
            return null;
        }

        foreach ([
            $root . '/plugins/' . $pluginName,
            $root . '/atom-ahg-plugins/' . $pluginName,
            $root . '/vendor/symfony/lib/plugins/' . $pluginName,
        ] as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Check if a plugin can be enabled.
     *
     * Enabling a plugin whose files are absent is not a recoverable error: on the
     * next request sfProjectConfiguration::getPluginPaths() throws
     * "The plugin ... does not exist", which takes down the web application AND
     * the CLI, leaving no route back except editing atom_plugin directly. That
     * happened on this instance on 4 September 2026 and cost a full outage.
     *
     * @return array{can_enable: bool, reason: string|null}
     */
    public function canEnable(string $pluginName, ?string $root = null): array
    {
        if (null === $this->findPluginDirectory($pluginName, $root)) {
            return [
                'can_enable' => false,
                'reason' => sprintf(
                    'Plugin files for "%s" are not present on this instance. Enabling it would stop Symfony booting and take the site down.',
                    $pluginName
                ),
            ];
        }

        return ['can_enable' => true, 'reason' => null];
    }

    /** Filesystem root of the AtoM instance, or null if it cannot be determined. */
    private function getAtomRoot(): ?string
    {
        $configFile = $this->findConfigFile();
        if (null !== $configFile) {
            return dirname($configFile, 2);
        }

        $atomRoot = getenv('ATOM_ROOT') ?: ($_SERVER['ATOM_ROOT'] ?? null);

        return $atomRoot && is_dir($atomRoot) ? $atomRoot : null;
    }

    /**
     * Check if a plugin can be disabled.
     *
     * @param string $pluginName Plugin name
     * @param bool $force Force disable even with records
     *
     * @return array{can_disable: bool, reason: string|null, record_count: int}
     */
    public function canDisable(string $pluginName, bool $force = false): array
    {
        // Check if core plugin
        if ($this->isCorePlugin($pluginName)) {
            return [
                'can_disable' => false,
                'reason' => 'Core plugin cannot be disabled',
                'record_count' => 0,
            ];
        }

        // Check if locked in database
        if ($this->isLocked($pluginName)) {
            return [
                'can_disable' => false,
                'reason' => 'Plugin is locked and cannot be disabled',
                'record_count' => 0,
            ];
        }

        // Check for existing records
        $recordCount = $this->getRecordCount($pluginName);
        if ($recordCount > 0 && !$force) {
            return [
                'can_disable' => false,
                'reason' => sprintf('Plugin has %s associated record(s)', number_format($recordCount)),
                'record_count' => $recordCount,
            ];
        }

        return [
            'can_disable' => true,
            'reason' => null,
            'record_count' => $recordCount,
        ];
    }

    /**
     * Check if plugin is a core plugin.
     */
    public function isCorePlugin(string $pluginName): bool
    {
        return in_array($pluginName, self::CORE_PLUGINS, true);
    }

    /**
     * Check if plugin is locked in the database.
     */
    public function isLocked(string $pluginName): bool
    {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare('SELECT is_locked FROM atom_plugin WHERE name = :name');
            $stmt->bindValue(':name', $pluginName, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row && (int) $row['is_locked'] === 1;
        } catch (\Exception $e) {
            error_log('ExtensionProtection::isLocked error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if plugin is a core plugin in the database.
     */
    public function isCoreInDatabase(string $pluginName): bool
    {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare('SELECT is_core FROM atom_plugin WHERE name = :name');
            $stmt->bindValue(':name', $pluginName, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row && (int) $row['is_core'] === 1;
        } catch (\Exception $e) {
            error_log('ExtensionProtection::isCoreInDatabase error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get the record count for a plugin.
     *
     * @param string $pluginName Plugin name
     *
     * @return int Number of records (0 if no check query or error)
     */
    public function getRecordCount(string $pluginName): int
    {
        try {
            $conn = $this->getConnection();

            // Get the record check query for this plugin
            $stmt = $conn->prepare('SELECT record_check_query FROM atom_plugin WHERE name = :name');
            $stmt->bindValue(':name', $pluginName, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['record_check_query'])) {
                return 0;
            }

            $checkQuery = $row['record_check_query'];

            // Validate query is a SELECT COUNT query for safety
            if (!$this->isValidCountQuery($checkQuery)) {
                error_log("Invalid record check query for {$pluginName}: {$checkQuery}");

                return 0;
            }

            // Execute the count query
            $countStmt = $conn->prepare($checkQuery);
            $countStmt->execute();
            $count = $countStmt->fetchColumn();

            return (int) $count;
        } catch (PDOException $e) {
            // Table might not exist - that's OK, means no records
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                return 0;
            }
            error_log("Error checking records for {$pluginName}: " . $e->getMessage());

            return 0;
        } catch (\Exception $e) {
            error_log("Error checking records for {$pluginName}: " . $e->getMessage());

            return 0;
        }
    }

    /**
     * Check if plugin has any associated records.
     */
    public function hasRecords(string $pluginName): bool
    {
        return $this->getRecordCount($pluginName) > 0;
    }

    /**
     * Validate that a query is a safe COUNT query.
     *
     * Only allows SELECT COUNT(*) FROM table_name patterns.
     */
    private function isValidCountQuery(string $query): bool
    {
        $query = trim(strtoupper($query));

        // Must start with SELECT COUNT
        if (strpos($query, 'SELECT COUNT') !== 0) {
            return false;
        }

        // Must not contain dangerous keywords
        $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', ';'];
        foreach ($dangerous as $keyword) {
            if (strpos($query, $keyword) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get protection status message for display.
     */
    public function getProtectionMessage(string $pluginName): ?string
    {
        $result = $this->canDisable($pluginName);

        if ($result['can_disable']) {
            return null;
        }

        return $result['reason'];
    }

    /**
     * Set record check query for a plugin.
     */
    public function setRecordCheckQuery(string $pluginName, string $query): bool
    {
        if (!$this->isValidCountQuery($query)) {
            return false;
        }

        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare('UPDATE atom_plugin SET record_check_query = :query WHERE name = :name');
            $stmt->bindValue(':query', $query, PDO::PARAM_STR);
            $stmt->bindValue(':name', $pluginName, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (\Exception $e) {
            error_log("Error setting record check query for {$pluginName}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Get all plugins with their protection status.
     *
     * @return array<string, array{can_disable: bool, reason: string|null, record_count: int, is_enabled: bool}>
     */
    public function getAllProtectionStatus(): array
    {
        $status = [];

        try {
            $conn = $this->getConnection();
            $stmt = $conn->query('SELECT name, is_enabled FROM atom_plugin');

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $pluginName = $row['name'];
                $result = $this->canDisable($pluginName);
                $status[$pluginName] = [
                    'can_disable' => $result['can_disable'],
                    'reason' => $result['reason'],
                    'record_count' => $result['record_count'],
                    'is_enabled' => (int) $row['is_enabled'] === 1,
                ];
            }
        } catch (\Exception $e) {
            error_log('ExtensionProtection::getAllProtectionStatus error: ' . $e->getMessage());
        }

        return $status;
    }
}
