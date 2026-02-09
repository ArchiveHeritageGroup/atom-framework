<?php

namespace AtomFramework\Actions;

use AtomExtensions\Helpers\CultureHelper;
use AtomFramework\Services\ConfigService;

/**
 * Base component class for AHG plugins.
 *
 * Extends sfComponents with automatic framework bootstrap and modern helpers.
 * Plugin component classes should extend this instead of sfComponents directly.
 *
 * Usage:
 *   class myComponents extends AhgComponents {
 *       public function executeWidget(sfWebRequest $request) {
 *           // Framework is already bootstrapped
 *           $culture = $this->culture();
 *       }
 *   }
 */
class AhgComponents extends \sfComponents
{
    protected static bool $frameworkBooted = false;

    /**
     * Auto-bootstrap the framework before every component.
     */
    public function preExecute()
    {
        parent::preExecute();

        if (!self::$frameworkBooted) {
            $bootstrap = \sfConfig::get('sf_root_dir') . '/atom-framework/bootstrap.php';
            if (file_exists($bootstrap)) {
                require_once $bootstrap;
            }
            self::$frameworkBooted = true;
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
     * Get the current user's culture.
     */
    protected function culture(): string
    {
        return CultureHelper::getCulture();
    }
}
