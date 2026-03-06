<?php

/**
 * Symfony 1.x sfBaseTask — standalone shim.
 *
 * Boots Laravel DB instead of Symfony. Provides the full sfBaseTask API
 * surface used by all 98 AHG plugin tasks so they run without base AtoM.
 *
 * API surface replicated:
 *   - configure(): namespace, name, briefDescription, detailedDescription
 *   - addOptions() / addArguments(): sfCommandOption / sfCommandArgument
 *   - execute($arguments, $options): main entry point
 *   - logSection() / log(): console output
 *   - createTask(): create subtask instance
 *   - $this->configuration: ProjectConfiguration compat
 */

if (!class_exists('sfBaseTask', false)) {
    abstract class sfBaseTask
    {
        // ── Properties used by plugin tasks ──────────────────────────

        protected $namespace = '';
        protected $name = '';
        protected $briefDescription = '';
        protected $detailedDescription = '';
        protected $options = [];
        protected $arguments = [];

        /** @var sfEventDispatcher */
        protected $dispatcher;

        /** @var sfFormatter */
        protected $formatter;

        /** @var sfContext|object|null */
        protected $context;

        /** @var object|null ProjectConfiguration compat */
        protected $configuration;

        // ── Constructor ──────────────────────────────────────────────

        public function __construct($dispatcher = null, $formatter = null)
        {
            $this->dispatcher = $dispatcher ?? new sfEventDispatcher();
            $this->formatter = $formatter ?? new sfFormatter();
            $this->configure();
        }

        // ── Configuration (overridden by each task) ──────────────────

        protected function configure()
        {
            // Override in task subclasses to set namespace, name, options, etc.
        }

        protected function addOptions(array $options): void
        {
            $this->options = array_merge($this->options, $options);
        }

        protected function addArguments(array $arguments): void
        {
            $this->arguments = array_merge($this->arguments, $arguments);
        }

        public function getFullName(): string
        {
            return $this->namespace ? $this->namespace . ':' . $this->name : $this->name;
        }

        public function getNamespace(): string
        {
            return $this->namespace;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getBriefDescription(): string
        {
            return $this->briefDescription;
        }

        public function getDetailedDescription(): string
        {
            return $this->detailedDescription;
        }

        public function getOptions(): array
        {
            return $this->options;
        }

        public function getArguments(): array
        {
            return $this->arguments;
        }

        // ── Execution ────────────────────────────────────────────────

        /**
         * Override in task subclasses.
         */
        abstract public function execute($arguments = [], $options = []);

        /**
         * Run the task with parsed arguments and options.
         * Called by TaskRunner after argument parsing.
         */
        public function run(array $arguments = [], array $options = []): int
        {
            $this->bootDatabase();
            $this->bootConfiguration();

            try {
                $result = $this->execute($arguments, $options);
                return is_int($result) ? $result : 0;
            } catch (\Exception $e) {
                $this->logSection('error', $e->getMessage());
                return 1;
            }
        }

        // ── Logging (1950+ usages of logSection across tasks) ────────

        protected function logSection(string $section, string $message, ?int $size = null, string $style = 'INFO'): void
        {
            $formatted = $this->formatter->formatSection($section, $message, $size, $style);
            echo $formatted . PHP_EOL;
        }

        protected function log($messages): void
        {
            if (!is_array($messages)) {
                $messages = [$messages];
            }
            foreach ($messages as $message) {
                echo $message . PHP_EOL;
            }
        }

        // ── Subtask creation ─────────────────────────────────────────

        protected function createTask(string $className): ?sfBaseTask
        {
            if (!class_exists($className)) {
                return null;
            }
            return new $className($this->dispatcher, $this->formatter);
        }

        // ── Database Boot ────────────────────────────────────────────

        protected function bootDatabase(): void
        {
            // Skip if already booted
            if (class_exists(\Illuminate\Database\Capsule\Manager::class, false)) {
                try {
                    \Illuminate\Database\Capsule\Manager::connection()->getPdo();
                    return;
                } catch (\Throwable $e) {
                    // Fall through to boot
                }
            }

            $rootDir = defined('SF_ROOT_DIR')
                ? SF_ROOT_DIR
                : (getenv('ATOM_ROOT') ?: '/usr/share/nginx/archive');

            // Use ConfigService if available (reads config/config.php properly)
            if (class_exists(\AtomExtensions\Services\ConfigService::class)) {
                $dbConfig = \AtomExtensions\Services\ConfigService::parseDbConfig($rootDir);
                if ($dbConfig) {
                    $capsule = new \Illuminate\Database\Capsule\Manager();
                    $capsule->addConnection($dbConfig);
                    $capsule->setAsGlobal();
                    $capsule->bootEloquent();
                    return;
                }
            }

            // Fallback: parse config/config.php directly
            $configFile = $rootDir . '/config/config.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
                $param = $config['all']['propel']['param'] ?? null;
                if ($param) {
                    $dsn = $param['dsn'] ?? '';
                    preg_match('/dbname=([^;]+)/', $dsn, $dbMatch);
                    preg_match('/host=([^;]+)/', $dsn, $hostMatch);
                    preg_match('/port=([^;]+)/', $dsn, $portMatch);

                    $capsule = new \Illuminate\Database\Capsule\Manager();
                    $capsule->addConnection([
                        'driver' => 'mysql',
                        'host' => $hostMatch[1] ?? '127.0.0.1',
                        'port' => (int) ($portMatch[1] ?? 3306),
                        'database' => $dbMatch[1] ?? 'archive',
                        'username' => $param['username'] ?? 'root',
                        'password' => $param['password'] ?? '',
                        'charset' => $param['encoding'] ?? 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'prefix' => '',
                    ]);
                    $capsule->setAsGlobal();
                    $capsule->bootEloquent();
                }
            }
        }

        // ── Configuration Boot ───────────────────────────────────────

        protected function bootConfiguration(): void
        {
            // Create a minimal configuration object if not already set
            if (!$this->configuration) {
                $this->configuration = new class {
                    public function isDebug(): bool
                    {
                        return false;
                    }

                    public function getPlugins(): array
                    {
                        try {
                            return \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                                ->where('is_enabled', 1)
                                ->pluck('name')
                                ->toArray();
                        } catch (\Throwable $e) {
                            return [];
                        }
                    }
                };
            }

            // Boot sfConfig if not already available
            if (class_exists('sfConfig', false) && !sfConfig::get('sf_root_dir')) {
                $rootDir = defined('SF_ROOT_DIR')
                    ? SF_ROOT_DIR
                    : (getenv('ATOM_ROOT') ?: '/usr/share/nginx/archive');

                sfConfig::set('sf_root_dir', $rootDir);
                sfConfig::set('sf_plugins_dir', $rootDir . '/plugins');
                sfConfig::set('sf_upload_dir', $rootDir . '/uploads');
                sfConfig::set('sf_data_dir', $rootDir . '/data');
                sfConfig::set('sf_cache_dir', $rootDir . '/cache');
                sfConfig::set('sf_log_dir', $rootDir . '/log');
                sfConfig::set('sf_web_dir', $rootDir);
                sfConfig::set('sf_environment', 'cli');
                sfConfig::set('sf_debug', false);

                // Load settings from database
                try {
                    $settings = \Illuminate\Database\Capsule\Manager::table('setting as s')
                        ->join('setting_i18n as si', function ($j) {
                            $j->on('s.id', '=', 'si.id')->where('si.culture', '=', 'en');
                        })
                        ->whereNotNull('s.name')
                        ->select('s.name', 'si.value')
                        ->get();

                    foreach ($settings as $row) {
                        sfConfig::set('app_' . $row->name, $row->value);
                    }
                } catch (\Throwable $e) {
                    // DB not available yet — skip
                }
            }
        }

        // ── Helpers used by arBaseTask subclasses ─────────────────────

        protected function getConnection(): \PDO
        {
            return \Illuminate\Database\Capsule\Manager::connection()->getPdo();
        }

        /**
         * Check if running in verbose mode.
         */
        protected function isVerbose(array $options): bool
        {
            return !empty($options['verbose']);
        }
    }
}
