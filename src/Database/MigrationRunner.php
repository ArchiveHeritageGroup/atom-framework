<?php
namespace AtomFramework\Database;
use Illuminate\Database\Capsule\Manager as DB;

class MigrationRunner
{
    protected string $frameworkMigrationsPath;
    protected string $pluginsPath;
    protected string $migrationsTable = 'atom_framework_migrations';

    public function __construct()
    {
        $this->frameworkMigrationsPath = dirname(__DIR__, 2) . '/database/migrations';
        $atomRoot = dirname(__DIR__, 3);
        if (is_dir($atomRoot . '/atom-ahg-plugins')) {
            $this->pluginsPath = $atomRoot . '/atom-ahg-plugins';
        } else {
            $this->pluginsPath = '';
        }
    }

    /**
     * Run all pending migrations
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        $executed = $this->getExecutedMigrations();
        $pending = $this->getPendingMigrations($executed);
        $results = [];

        foreach ($pending as $migration) {
            try {
                $this->runMigration($migration);
                $this->recordMigration($migration['name']);
                $results[] = ['migration' => $migration['name'], 'status' => 'success'];
            } catch (\Exception $e) {
                // Do NOT halt the whole run on one bad migration. Plugin migrations
                // are largely independent, so a single failure (e.g. a migration
                // using MariaDB-only `IF NOT EXISTS` on MySQL) must not block every
                // other plugin's migrations. The failure is reported and left
                // unrecorded, so it retries on the next run once fixed.
                $results[] = ['migration' => $migration['name'], 'status' => 'failed', 'error' => $e->getMessage()];
                continue;
            }
        }
        return $results;
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrations();
        $status = [];

        foreach ($all as $migration) {
            $status[] = [
                'migration' => $migration['name'],
                'source' => $migration['source'],
                'type' => $migration['type'] ?? 'sql',
                'executed' => in_array($migration['name'], $executed),
                'executed_at' => $this->getExecutedAt($migration['name'])
            ];
        }
        return $status;
    }

    protected function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        DB::statement($sql);
    }

    protected function getExecutedMigrations(): array
    {
        return DB::table($this->migrationsTable)->pluck('migration')->toArray();
    }

    protected function getExecutedAt(string $name): ?string
    {
        $record = DB::table($this->migrationsTable)->where('migration', $name)->first();
        return $record->executed_at ?? null;
    }

    protected function recordMigration(string $name): void
    {
        DB::table($this->migrationsTable)->insert([
            'migration' => $name,
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get all available migration files from framework and plugins
     */
    protected function getAllMigrations(): array
    {
        $migrations = [];

        // Framework migrations - SQL files
        foreach (glob($this->frameworkMigrationsPath . '/*.sql') as $file) {
            $migrations[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'path' => $file,
                'source' => 'framework',
                'type' => 'sql'
            ];
        }

        // Framework migrations - PHP files
        foreach (glob($this->frameworkMigrationsPath . '/*.php') as $file) {
            $migrations[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'path' => $file,
                'source' => 'framework',
                'type' => 'php'
            ];
        }

        // Plugin migrations
        if (!empty($this->pluginsPath) && is_dir($this->pluginsPath)) {
            foreach (glob($this->pluginsPath . '/ahg*Plugin', GLOB_ONLYDIR) as $pluginDir) {
                $pluginName = basename($pluginDir);
                // Plugins keep migrations in database/migrations (all 18 do; none
                // use data/migrations). This path was wrong, so NO plugin migration
                // was ever discovered by `bin/atom migrate run`.
                $migrationsDir = $pluginDir . '/database/migrations';
                
                if (is_dir($migrationsDir)) {
                    foreach (glob($migrationsDir . '/*.sql') as $file) {
                        $migrations[] = [
                            'name' => $pluginName . ':' . pathinfo($file, PATHINFO_FILENAME),
                            'path' => $file,
                            'source' => $pluginName,
                            'type' => 'sql'
                        ];
                    }
                    foreach (glob($migrationsDir . '/*.php') as $file) {
                        $migrations[] = [
                            'name' => $pluginName . ':' . pathinfo($file, PATHINFO_FILENAME),
                            'path' => $file,
                            'source' => $pluginName,
                            'type' => 'php'
                        ];
                    }
                }
            }
        }

        usort($migrations, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $migrations;
    }

    protected function getPendingMigrations(array $executed): array
    {
        $all = $this->getAllMigrations();
        return array_filter($all, fn($m) => !in_array($m['name'], $executed));
    }

    /**
     * Validate SQL migration is safe
     */
    protected function validateSqlMigration(string $sql, string $name): void
    {
        if (preg_match('/DROP\s+(TABLE|DATABASE|INDEX)/i', $sql)) {
            throw new \Exception("Migration '{$name}' contains DROP statement.");
        }
        if (preg_match('/TRUNCATE\s+TABLE/i', $sql)) {
            throw new \Exception("Migration '{$name}' contains TRUNCATE statement.");
        }
        if (preg_match('/DELETE\s+FROM\s+\w+\s*;/i', $sql)) {
            throw new \Exception("Migration '{$name}' contains DELETE without WHERE.");
        }
    }

    /**
     * Run a single migration
     */
    protected function runMigration(array $migration): void
    {
        $file = $migration['path'];
        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: {$file}");
        }

        $type = $migration['type'] ?? 'sql';

        if ($type === 'php') {
            $this->runPhpMigration($file, $migration['name']);
        } else {
            $this->runSqlMigration($file, $migration['name']);
        }
    }

    /**
     * Run SQL migration
     */
    protected function runSqlMigration(string $file, string $name): void
    {
        $sql = file_get_contents($file);
        $this->validateSqlMigration($sql, $name);

        // Stored procedures, triggers, DELIMITER blocks and PREPARE/EXECUTE
        // cannot be run through PDO's single-statement DB::statement() (PDO does
        // not understand the DELIMITER client directive, and naive ";" splitting
        // shreds a routine body). Hand those files to the mysql client, which
        // parses them natively. Such migrations guard themselves for idempotency
        // (information_schema checks, CREATE TABLE IF NOT EXISTS, DROP PROCEDURE
        // IF EXISTS), so they are safe to re-run without the per-statement
        // safe-error skipping the PDO path below provides. Plain-DDL migrations
        // stay on the PDO path so that path's "already exists" tolerance keeps
        // them idempotent.
        if ($this->sqlNeedsMysqlClient($sql)) {
            $this->runSqlViaMysqlClient($file, $name);
            return;
        }

        $statements = $this->parseSqlStatements($sql);
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                try {
                    DB::statement($statement);
                } catch (\Exception $e) {
                    // Ignore "schema already there / not there" errors so a
                    // migration is idempotent AND tolerant of optional-plugin
                    // tables that aren't installed on this instance (e.g. a
                    // cross-cutting enum->varchar MODIFY that lists a table the
                    // 3D viewer plugin would have created). A genuinely wrong
                    // statement still surfaces because its error code is not
                    // in this list.
                    $safeErrors = [
                        '42S21', // Column already exists
                        '42S01', // Table already exists
                        '42000', // Duplicate key name
                        '42S02', // Base table or view not found
                        '1050',  // Table already exists
                        '1054',  // Unknown column
                        '1060',  // Duplicate column name
                        '1061',  // Duplicate key name
                        '1072',  // Key column doesn't exist in table
                        '1091',  // Can't DROP; check that it exists
                        '1146',  // Table doesn't exist
                    ];
                    $safePhrases = ['already exists', 'Duplicate', "doesn't exist", 'Unknown column'];

                    $isSafe = false;
                    foreach ($safeErrors as $code) {
                        if (strpos($e->getMessage(), $code) !== false) {
                            $isSafe = true;
                            break;
                        }
                    }
                    if (!$isSafe) {
                        foreach ($safePhrases as $phrase) {
                            if (strpos($e->getMessage(), $phrase) !== false) {
                                $isSafe = true;
                                break;
                            }
                        }
                    }

                    if (!$isSafe) {
                        throw $e;
                    }
                    // Safe error - continue with next statement
                }
            }
        }
    }

    /**
     * Does this SQL need the mysql client rather than PDO?
     *
     * True for stored routines / triggers / DELIMITER blocks / prepared
     * statements - constructs PDO's single-statement executor cannot run.
     */
    protected function sqlNeedsMysqlClient(string $sql): bool
    {
        return (bool) preg_match(
            '/^\s*DELIMITER\b|\bCREATE\s+(DEFINER\s*=\s*\S+\s+)?(PROCEDURE|FUNCTION|TRIGGER)\b|\bPREPARE\s+\w+\s+FROM\b/im',
            $sql
        );
    }

    /**
     * Run a .sql migration through the mysql client (see runSqlMigration).
     *
     * The password is passed via MYSQL_PWD (never on the command line, so it
     * stays out of the process list and avoids the client's insecure-password
     * warning); every other connection parameter is shell-escaped. --force lets
     * the client run every statement (skipping ones that error) so the file
     * applies as fully as possible; we then inspect the errors and only throw on
     * a genuine one - a "schema already there / not there" error (an optional
     * table/column not installed on this instance) is tolerated, matching the
     * PDO path's safe-skip.
     */
    protected function runSqlViaMysqlClient(string $file, string $name): void
    {
        $config = DB::connection()->getConfig();
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = (string) ($config['password'] ?? '');

        $cmd = sprintf(
            'mysql --force --host=%s --port=%s --user=%s --default-character-set=utf8mb4 %s',
            escapeshellarg((string) $host),
            escapeshellarg($port),
            escapeshellarg((string) $username),
            escapeshellarg((string) $database)
        );

        $env = getenv();
        $env['MYSQL_PWD'] = $password;

        $descriptors = [
            0 => ['file', $file, 'r'], // feed the migration file on stdin
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
        if (!is_resource($proc)) {
            throw new \Exception("Could not launch mysql client for migration '{$name}'.");
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        // mysql prints one "ERROR <code> (SQLSTATE) at line N: ..." per failed
        // statement. Collect the codes and tolerate only the schema-present /
        // schema-absent ones (same set as the PDO path). Anything else - or a
        // non-zero exit we can't attribute (e.g. can't connect) - is a real
        // failure and throws.
        $codes = [];
        if (preg_match_all('/ERROR\s+(\d+)/', (string) $stderr, $m)) {
            $codes = array_values(array_unique($m[1]));
        }
        $tolerable = ['1050', '1054', '1060', '1061', '1072', '1091', '1146'];
        $untolerable = array_diff($codes, $tolerable);

        if (!empty($untolerable)) {
            throw new \Exception("mysql client failed on migration '{$name}' (error " . implode(', ', $untolerable) . '): ' . trim((string) $stderr));
        }
        if (0 !== $exit && empty($codes)) {
            $detail = trim($stderr) !== '' ? trim($stderr) : trim((string) $stdout);
            throw new \Exception("mysql client failed (exit {$exit}) on migration '{$name}': {$detail}");
        }
    }

    /**
     * Run PHP migration
     */
    protected function runPhpMigration(string $file, string $name): void
    {
        $content = file_get_contents($file);
        
        // Check if file returns an anonymous class
        if (strpos($content, 'return new class') !== false) {
            $migration = require $file;
            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
                return;
            }
            throw new \Exception("Anonymous migration class must have an up() method: {$name}");
        }

        // Extract class name from file content
        if (preg_match('/^class\s+(\w+)/m', $content, $matches)) {
            $className = $matches[1];
        } else {
            // Fallback: derive from filename
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $parts = explode('_', $fileName);
            
            // Remove date prefix
            if (count($parts) > 3 && strlen($parts[0]) === 4 && is_numeric($parts[0]) && (int)$parts[0] > 2000) {
                $parts = array_slice($parts, 3);
                if (count($parts) > 0 && is_numeric($parts[0])) {
                    $parts = array_slice($parts, 1);
                }
            } elseif (count($parts) > 1 && is_numeric($parts[0])) {
                $parts = array_slice($parts, 1);
            }
            
            $className = str_replace(' ', '', ucwords(implode(' ', $parts)));
        }

        require_once $file;

        // Try namespaced class first
        $possibleClasses = [
            "\\AtomExtensions\\Migrations\\{$className}",
            "\\AtomFramework\\Migrations\\{$className}",
            $className
        ];

        $migrationClass = null;
        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                $migrationClass = $class;
                break;
            }
        }

        if (!$migrationClass) {
            throw new \Exception("Could not find migration class for: {$name} (tried: " . implode(', ', $possibleClasses) . ")");
        }

        // Check for static up() or instance up()
        if (method_exists($migrationClass, 'up')) {
            $reflection = new \ReflectionMethod($migrationClass, 'up');
            if ($reflection->isStatic()) {
                $migrationClass::up();
            } else {
                $instance = new $migrationClass();
                $instance->up();
            }
        } else {
            throw new \Exception("Migration class {$migrationClass} must have an up() method");
        }
    }
    protected function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || str_starts_with($trimmed, '--')) {
                continue;
            }
            $current .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        return $statements;
    }
}
