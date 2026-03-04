<?php

/**
 * Symfony 1.x CLI command classes — standalone stubs.
 *
 * Provides sfCommandOption, sfCommandArgument, sfFormatter,
 * sfEventDispatcher so plugin task files can be loaded without Symfony.
 */

// ── sfCommandOption ──────────────────────────────────────────────

if (!class_exists('sfCommandOption', false)) {
    class sfCommandOption
    {
        public const PARAMETER_NONE = 1;
        public const PARAMETER_REQUIRED = 2;
        public const PARAMETER_OPTIONAL = 4;
        public const IS_ARRAY = 8;

        public string $name;
        public ?string $shortcut;
        public int $mode;
        public string $help;
        public $default;

        public function __construct(string $name, ?string $shortcut = null, int $mode = self::PARAMETER_NONE, string $help = '', $default = null)
        {
            $this->name = $name;
            $this->shortcut = $shortcut;
            $this->mode = $mode;
            $this->help = $help;
            $this->default = $default;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getShortcut(): ?string
        {
            return $this->shortcut;
        }

        public function acceptParameter(): bool
        {
            return self::PARAMETER_NONE !== ($this->mode & ~self::IS_ARRAY);
        }

        public function isParameterRequired(): bool
        {
            return self::PARAMETER_REQUIRED === ($this->mode & ~self::IS_ARRAY);
        }

        public function isParameterOptional(): bool
        {
            return self::PARAMETER_OPTIONAL === ($this->mode & ~self::IS_ARRAY);
        }

        public function isArray(): bool
        {
            return (bool) ($this->mode & self::IS_ARRAY);
        }

        public function getDefault()
        {
            return $this->default;
        }

        public function getHelp(): string
        {
            return $this->help;
        }
    }
}

// ── sfCommandArgument ────────────────────────────────────────────

if (!class_exists('sfCommandArgument', false)) {
    class sfCommandArgument
    {
        public const REQUIRED = 1;
        public const OPTIONAL = 2;
        public const IS_ARRAY = 4;

        public string $name;
        public int $mode;
        public string $help;
        public $default;

        public function __construct(string $name, int $mode = self::OPTIONAL, string $help = '', $default = null)
        {
            $this->name = $name;
            $this->mode = $mode;
            $this->help = $help;
            $this->default = $default;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function isRequired(): bool
        {
            return self::REQUIRED === ($this->mode & ~self::IS_ARRAY);
        }

        public function getDefault()
        {
            return $this->default;
        }
    }
}

// ── sfFormatter ──────────────────────────────────────────────────

if (!class_exists('sfFormatter', false)) {
    class sfFormatter
    {
        protected int $maxLineSize = 2048;

        public function setMaxLineSize(int $size): void
        {
            $this->maxLineSize = $size;
        }

        public function format(string $text, $parameters = null): string
        {
            return $text;
        }

        public function formatSection(string $section, string $text, ?int $size = null, string $style = 'INFO'): string
        {
            return sprintf('>> %-10s %s', $section, $text);
        }

        public function setStyle(string $name, array $options = []): void
        {
            // No-op in standalone
        }
    }
}

// ── sfEventDispatcher (minimal) ──────────────────────────────────

if (!class_exists('sfEventDispatcher', false)) {
    class sfEventDispatcher
    {
        public function connect(string $name, $listener): void {}
        public function disconnect(string $name, $listener): bool { return true; }
        public function notify($event): void {}
        public function filter($event, $value) { return $value; }
        public function hasListeners(string $name): bool { return false; }
        public function getListeners(string $name): array { return []; }
    }
}

// ── sfEvent (minimal) ────────────────────────────────────────────

if (!class_exists('sfEvent', false)) {
    class sfEvent
    {
        protected $subject;
        protected string $name;
        protected array $parameters;

        public function __construct($subject = null, string $name = '', array $parameters = [])
        {
            $this->subject = $subject;
            $this->name = $name;
            $this->parameters = $parameters;
        }

        public function getName(): string { return $this->name; }
        public function getSubject() { return $this->subject; }
        public function getParameters(): array { return $this->parameters; }
    }
}

// ── sfDatabaseManager ────────────────────────────────────────────

if (!class_exists('sfDatabaseManager', false)) {
    class sfDatabaseManager
    {
        public function __construct($configuration = null, $autoconnect = true)
        {
            // No-op — Laravel Capsule manages connections.
            // Tasks that call `new sfDatabaseManager($this->configuration)`
            // just need this to not crash. The DB is already booted by sfBaseTask::run().
        }

        public function getDatabase(string $name = 'propel'): ?object
        {
            return null;
        }

        public function initialize()
        {
            // No-op
        }

        public function shutdown()
        {
            // No-op
        }
    }
}

// ── ProjectConfiguration ─────────────────────────────────────────

if (!class_exists('ProjectConfiguration', false)) {
    class ProjectConfiguration
    {
        protected static ?self $instance = null;

        public static function getApplicationConfiguration(string $app = 'qubit', string $env = 'cli', bool $debug = false): self
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function getActive(): self
        {
            return self::$instance ?? new self();
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

        public function isPluginEnabled(string $name): bool
        {
            return in_array($name, $this->getPlugins());
        }

        public function isDebug(): bool
        {
            return false;
        }

        public function getEnvironment(): string
        {
            return 'cli';
        }
    }
}

// ── sfContext CLI stub ────────────────────────────────────────────
// Tasks call sfContext::createInstance($configuration). In standalone CLI,
// we create a minimal context that provides getUser(), getRequest(), etc.

if (!class_exists('sfContext', false)) {
    class sfContext
    {
        private static ?self $instance = null;
        private $configuration;
        private $user;

        public static function createInstance($configuration): self
        {
            self::$instance = new self();
            self::$instance->configuration = $configuration;
            return self::$instance;
        }

        public static function getInstance(): self
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function hasInstance(): bool
        {
            return null !== self::$instance;
        }

        public function getUser()
        {
            if (!$this->user) {
                $this->user = new class {
                    public function getCulture(): string { return 'en'; }
                    public function isAuthenticated(): bool { return true; }
                    public function isAdministrator(): bool { return true; }
                    public function hasCredential($c): bool { return true; }
                    public function setAttribute($k, $v, $ns = null): void {}
                    public function getAttribute($k, $d = null, $ns = null) { return $d; }
                };
            }
            return $this->user;
        }

        public function getConfiguration() { return $this->configuration ?? new ProjectConfiguration(); }
        public function getModuleName(): string { return ''; }
        public function getActionName(): string { return ''; }
        public function getRouting() { return new class { public function generate($n, $p = []): string { return '/'; } }; }
        public function getResponse() { return new class { public function setHttpHeader($k, $v): void {} }; }
        public function getRequest() { return new class { public function getParameter($k, $d = null) { return $d; } }; }
        public function getController() { return new class { public function redirect($u, $d = 0, $s = 302): void {} public function forward($m, $a): void {} public function genUrl($p): string { return is_string($p) ? $p : '/'; } }; }
        public function getLogger() { return new class { public function err($m): void { error_log($m); } public function info($m): void {} public function debug($m): void {} }; }
        public function getEventDispatcher() { return new sfEventDispatcher(); }
        public function getI18N() { return new class { public function __($t, $a = []) { return $t; } }; }
        public function get(string $name) { return null; }
        public function has(string $name): bool { return false; }
    }
}

// ── sfCommandManager (argument parsing) ──────────────────────────

if (!class_exists('sfCommandManager', false)) {
    class sfCommandManager
    {
        protected array $optionValues = [];
        protected array $argumentValues = [];
        protected array $errors = [];

        public function process($argv): void
        {
            // Parsed externally by TaskRunner
        }

        public function getOptionValues(): array
        {
            return $this->optionValues;
        }

        public function getArgumentValues(): array
        {
            return $this->argumentValues;
        }

        public function getErrors(): array
        {
            return $this->errors;
        }
    }
}
