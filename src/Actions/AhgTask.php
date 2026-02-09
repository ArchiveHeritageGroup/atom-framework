<?php

namespace AtomFramework\Actions;

use AtomFramework\Services\ConfigService;

/**
 * Base task class for AHG CLI commands.
 *
 * Extends sfBaseTask with framework bootstrap and modern helpers.
 * Plugin task classes should extend this instead of sfBaseTask directly.
 *
 * Usage:
 *   class myCustomTask extends AhgTask {
 *       protected function configure() {
 *           $this->addArguments([...]);
 *           $this->namespace = 'my';
 *           $this->name = 'custom';
 *       }
 *
 *       protected function execute($arguments = [], $options = []) {
 *           $this->initFramework();
 *           // Framework is now bootstrapped
 *       }
 *   }
 */
abstract class AhgTask extends \sfBaseTask
{

    /**
     * Bootstrap the framework (call from execute).
     *
     * Unlike AhgActions/AhgComponents, tasks must call this explicitly
     * because sfBaseTask::execute() does not have a preExecute hook.
     */
    protected function initFramework(): void
    {
        $bootstrap = \sfConfig::get('sf_root_dir') . '/atom-framework/bootstrap.php';
        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }
    }

    // ─── Service Access ──────────────────────────────────────────────

    /**
     * Get a configuration value.
     */
    protected function config(string $key, $default = null)
    {
        return ConfigService::get($key, $default);
    }

    /**
     * Output JSON to the CLI.
     */
    protected function renderJson(array $data): void
    {
        $this->log(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
