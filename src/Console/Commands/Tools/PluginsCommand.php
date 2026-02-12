<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Manage AtoM plugins stored in the database.
 *
 * Ported from lib/task/tools/atomPluginsTask.class.php.
 * Uses Propel for QubitSetting access to the legacy plugin list.
 */
class PluginsCommand extends BaseCommand
{
    protected string $name = 'tools:plugins';
    protected string $description = 'Manage AtoM plugins (add, delete, list)';
    protected string $detailedDescription = <<<'EOF'
Manage AtoM plugins stored in the database (legacy setting_i18n).

Actions:
    list    - List all currently enabled plugins
    add     - Add a plugin to the enabled list
    delete  - Remove a plugin from the enabled list

Examples:
    php bin/atom tools:plugins list
    php bin/atom tools:plugins add ahgFoobarPlugin
    php bin/atom tools:plugins delete ahgFoobarPlugin
EOF;

    protected function configure(): void
    {
        $this->addArgument('action', 'The action (add, delete, or list)', true);
        $this->addArgument('plugin', 'The plugin name (required for add/delete)');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $action = $this->argument('action');
        $pluginName = $this->argument('plugin');

        // Retrieve QubitSetting object
        $criteria = new \Criteria();
        $criteria->add(\QubitSetting::NAME, 'plugins');
        $setting = \QubitSetting::getOne($criteria);

        if (null === $setting) {
            throw new \RuntimeException('Database entry could not be found.');
        }

        // Array of plugins
        $plugins = array_values(unserialize($setting->getValue(['sourceCulture' => true])) ?: []);

        if (in_array($action, ['add', 'delete']) && empty($pluginName)) {
            throw new \RuntimeException('Missing plugin name.');
        }

        switch ($action) {
            case 'add':
                $plugins[] = $pluginName;

                // Save changes
                $setting->setValue(serialize(array_unique($plugins)), ['sourceCulture' => true]);
                $setting->save();

                $this->success("Plugin '{$pluginName}' added.");
                break;

            case 'delete':
                $key = array_search($pluginName, $plugins);
                if (false !== $key) {
                    unset($plugins[$key]);
                } else {
                    throw new \RuntimeException('Plugin could not be found.');
                }

                // Save changes
                $setting->setValue(serialize(array_unique($plugins)), ['sourceCulture' => true]);
                $setting->save();

                $this->success("Plugin '{$pluginName}' deleted.");
                break;

            case 'list':
                foreach ($plugins as $plugin) {
                    $this->line($plugin);
                }
                break;

            default:
                throw new \RuntimeException("Unknown action: '{$action}'. Use add, delete, or list.");
        }

        return 0;
    }
}
