<?php

namespace AtomFramework\Services;

/**
 * The command that runs an AHG plugin task in the background.
 *
 * `php symfony <task>` only knows the AHG tasks when ProjectConfiguration loads
 * the plugins from the database. On a stock ProjectConfiguration, which
 * bin/install has left untouched since August 2026, the AHG namespaces do not
 * exist at the command line ("There are no tasks defined in the "ingest"
 * namespace"). Every web action that started a task in the background then
 * failed silently: the ingest commit stayed at 0%, and backup, restore and
 * portable export never ran.
 *
 * bin/ahg-cli.php enables the database's plugins before symfony builds its task
 * list, so it finds the AHG tasks on any install and runs the base tasks
 * unchanged. Use this wherever a task is started with exec(), nohup or the queue.
 */
class CliTask
{
    /**
     * `php '<runner>'`, to be followed by the task name and its options.
     */
    public static function command(?string $root = null): string
    {
        $root = rtrim($root ?: self::root(), '/');

        foreach (['/atom-framework/bin/ahg-cli.php', '/plugins/ahgRuntimePlugin/bin/ahg'] as $runner) {
            if (is_file($root.$runner)) {
                return 'php '.escapeshellarg($root.$runner);
            }
        }

        return 'php '.escapeshellarg($root.'/symfony');
    }

    private static function root(): string
    {
        if (class_exists('sfConfig', false) && \sfConfig::get('sf_root_dir')) {
            return \sfConfig::get('sf_root_dir');
        }

        return defined('ATOM_ROOT') ? ATOM_ROOT : dirname(__DIR__, 3);
    }
}
