<?php

/*
 * CLI entry point for AHG plugin tasks on a packaged install.
 *
 * THE PROBLEM
 *
 * symfony builds its task list in sfSymfonyCommandApplication::loadTasks(),
 * which walks the plugin paths held by ProjectConfiguration and picks up any
 * lib/task directory it finds. On a packaged install ProjectConfiguration is
 * stock AtoM, so that list is the base ten plugins and nothing else:
 *
 *     $ php symfony preservation:identify
 *       There are no tasks defined in the "preservation" namespace.
 *
 * The plugins are enabled - they work perfectly through the browser - but they
 * are enabled by sfPluginAdminPluginConfiguration::initialize(), which reads the
 * `plugins` setting from the database and returns early unless the configuration
 * is an sfApplicationConfiguration. That is true of a web request and false of
 * the CLI, so at the command line the database list is never consulted and the
 * plugins may as well not exist.
 *
 * The effect is worst exactly where it matters least visibly. Fixity checking
 * and virus scanning are scheduled jobs by nature, so a preservation plugin
 * whose fixity task cannot run from cron is missing the point of itself.
 *
 * THE FIX
 *
 * Do at the command line what sfPluginAdminPlugin does for a web request: read
 * the same `plugins` setting, keep the entries that exist on disk, and enable
 * them before symfony builds its task list.
 *
 * Nothing in base AtoM is modified. This is a second entry point beside
 * `symfony`, not a replacement for it, and it defers to the same command
 * application - so every base task still works through it and behaves
 * identically.
 *
 * USAGE
 *
 *     php plugins/ahgRuntimePlugin/bin/ahg preservation:fixity --help
 *     php plugins/ahgRuntimePlugin/bin/ahg list
 *
 * In cron, use the absolute path and the site's own user:
 *
 *     0 2 * * *  www-data  cd /path/to/atom && \
 *                php plugins/ahgRuntimePlugin/bin/ahg preservation:fixity
 */

// The AtoM root: three levels up from plugins/<plugin>/bin/.
$root = dirname(__DIR__, 3);

if (!file_exists($root.'/config/ProjectConfiguration.class.php')) {
    fwrite(STDERR, "Cannot find the AtoM root from ".__DIR__."\n");
    fwrite(STDERR, "Run this from inside a plugins/<plugin>/bin/ directory of an AtoM install.\n");
    exit(1);
}

chdir($root);
require_once $root.'/config/ProjectConfiguration.class.php';

/**
 * ProjectConfiguration with the database's enabled plugins added.
 *
 * setup() is the only place the plugin list can be changed: symfony fixes it
 * immediately afterwards, before any plugin configuration class is constructed.
 */
class AhgCliConfiguration extends ProjectConfiguration
{
    public function setup()
    {
        parent::setup();

        $plugins = $this->getPlugins();

        foreach ($this->ahgEnabledPlugins() as $plugin) {
            // Only what is actually on disk. A row for a plugin that has been
            // removed would otherwise abort the whole CLI with a path error,
            // which is a poor trade for a stale database row.
            if (in_array($plugin, $plugins, true)) {
                continue;
            }

            if (is_dir($this->getRootDir().'/plugins/'.$plugin)) {
                $plugins[] = $plugin;
            }
        }

        $this->enablePlugins($plugins);
    }

    /**
     * The `plugins` setting, read directly.
     *
     * PDO rather than the query builder or Propel: this runs during setup(),
     * before any of them exist. The connection details come from config.php,
     * which is where AtoM 2.9 and later keep them - databases.yml is a zero-byte
     * file on those versions and reading it finds nothing.
     */
    protected function ahgEnabledPlugins(): array
    {
        $configFile = $this->getRootDir().'/config/config.php';

        if (!file_exists($configFile)) {
            return [];
        }

        $config = include $configFile;
        $params = $config['all']['propel']['param'] ?? null;

        if (!is_array($params) || empty($params['dsn'])) {
            return [];
        }

        try {
            $pdo = new PDO($params['dsn'], $params['username'] ?? '', $params['password'] ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->query(
                'SELECT si.value FROM setting s '.
                'JOIN setting_i18n si ON si.id = s.id '.
                "WHERE s.name = 'plugins' LIMIT 1"
            );

            $value = $stmt ? $stmt->fetchColumn() : null;

            if (!$value) {
                return [];
            }

            $plugins = unserialize($value);

            return is_array($plugins) ? $plugins : [];
        } catch (Throwable $e) {
            // A CLI that still runs the base tasks beats one that refuses to
            // start because the database is unreachable.
            fwrite(STDERR, "Warning: could not read enabled plugins - ".$e->getMessage()."\n");

            return [];
        }
    }
}

/**
 * The command application, built on the configuration above.
 *
 * symfony's own cli.php cannot be reused here. It calls
 *
 *     new sfSymfonyCommandApplication($dispatcher, null, ...)
 *
 * passing null for the configuration, and sfSymfonyCommandApplication::configure()
 * then hardcodes `new ProjectConfiguration(getcwd())` before calling loadTasks()
 * on it. Any configuration prepared beforehand is discarded, which is why
 * subclassing ProjectConfiguration alone changed nothing: the object was built
 * and then never used.
 *
 * Overriding configure() is the whole fix. Everything else - task discovery,
 * option handling, error rendering - is symfony's, unchanged.
 */
class AhgCommandApplication extends sfSymfonyCommandApplication
{
    public function configure()
    {
        if (!isset($this->options['symfony_lib_dir'])) {
            throw new sfInitializationException('You must pass a "symfony_lib_dir" option.');
        }

        $this->setName('symfony');
        $this->setVersion(SYMFONY_VERSION);

        $this->loadTasks(new AhgCliConfiguration(getcwd(), $this->dispatcher));
    }
}

try {
    $dispatcher = new sfEventDispatcher();
    $logger = new sfCommandLogger($dispatcher);

    $application = new AhgCommandApplication($dispatcher, null, [
        'symfony_lib_dir' => realpath(sfCoreAutoload::getInstance()->getBaseDir()),
    ]);

    $statusCode = $application->run();
} catch (Exception $e) {
    if (!isset($application)) {
        throw $e;
    }

    $application->renderException($e);
    $statusCode = $e->getCode();

    exit(is_numeric($statusCode) && $statusCode ? $statusCode : 1);
}

exit(is_numeric($statusCode) ? $statusCode : 0);
