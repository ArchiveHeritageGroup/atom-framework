<?php

declare(strict_types=1);

namespace AtomFramework\Config;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Keeps AtoM's `plugins` setting in step with the atom_plugin table.
 *
 * WHY THIS EXISTS
 *
 * AtoM enables plugins from the database already, and has done since long before
 * this project: sfPluginAdminPlugin is in AtoM's own hardcoded plugin list, and
 * its initialize() reads the `plugins` setting and calls enablePlugins() on
 * whatever it finds. That is how arDominionB5Plugin and the descriptive-standard
 * plugins come to be loaded, none of which appear in ProjectConfiguration.
 *
 * So the AHG plugins do not need a modified ProjectConfiguration in order to
 * load. They need to be named in that setting, and the framework autoloader has
 * to exist before the first one initialises - which
 * ahgCorePluginConfiguration::bootstrapFramework() now handles.
 *
 * What that leaves is two records of which plugins are on: atom_plugin, which
 * this project treats as the source of truth and which the admin UI reads, and
 * the `plugins` setting, which is what actually decides loading. They drift.
 * Measured on a live instance 2026-08-05, three plugins were enabled in
 * atom_plugin and absent from the setting, so their routes 404'd while the admin
 * screen showed them enabled - which reads as a broken plugin and is not one.
 *
 * ExtensionManager keeps them aligned on enable and disable. This class exists
 * for everything that does not go through ExtensionManager: bin/install, a
 * restored database, a direct SQL update, or an instance cloned from another.
 *
 * ONE RULE WORTH KNOWING
 *
 * A plugin named in the setting with no atom_plugin row at all is reported as
 * unmanaged and left alone. It is not evidence of a stale entry - it is more
 * likely a plugin enabled before this table was in use, and dropping it would
 * turn working functionality off. Only an explicit is_enabled = 0 removes an
 * entry.
 */
final class PluginActivation
{
    /**
     * The setting sfPluginAdminPlugin reads, as [id, [culture => value]].
     */
    private static function settingRow(): ?array
    {
        $setting = DB::table('setting')->where('name', 'plugins')->first();

        if (!$setting) {
            return null;
        }

        $rows = DB::table('setting_i18n')->where('id', $setting->id)->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return [$setting->id, $rows];
    }

    /**
     * The plugin list AtoM is currently loading.
     */
    public static function current(): array
    {
        $row = self::settingRow();

        if (null === $row) {
            return [];
        }

        foreach ($row[1] as $record) {
            if (empty($record->value)) {
                continue;
            }

            // Plugin names only; no objects (security audit 2026-06-15).
            $plugins = @unserialize($record->value, ['allowed_classes' => false]);

            if (is_array($plugins)) {
                return array_values($plugins);
            }
        }

        return [];
    }

    /**
     * What the list should be, given atom_plugin and what is on disk.
     */
    public static function desired(string $rootDir, bool $prune = false): array
    {
        $current = self::current();

        $enabled = [];
        $disabled = [];

        foreach (DB::table('atom_plugin')->get() as $record) {
            if ((int) $record->is_enabled === 1) {
                $enabled[$record->name] = true;
            } else {
                $disabled[$record->name] = true;
            }
        }

        $plugins = [];

        foreach ($current as $name) {
            // A plugin that is loading stays loading unless removal was asked
            // for. The table is not reliably right about this: measured on a
            // live instance, ahgIiifPlugin was marked is_enabled = 0 while
            // plainly loading and serving manifests, and trusting the table
            // would have switched IIIF off - along with the Seadragon and
            // Mirador plugins that depend on it. Adding what is missing repairs
            // a 404; removing what is present breaks a working page, so the two
            // do not belong behind the same default.
            if ($prune && isset($disabled[$name])) {
                continue;
            }

            $plugins[] = $name;
        }

        // Anything enabled in the table and not yet in the list.
        foreach (array_keys($enabled) as $name) {
            if (!in_array($name, $plugins, true)) {
                $plugins[] = $name;
            }
        }

        // A name with no directory makes sfPluginAdminPlugin throw, so drop it
        // here rather than let it take the site down.
        $plugins = array_values(array_filter($plugins, static function ($name) use ($rootDir) {
            return is_dir($rootDir.'/plugins/'.$name)
                || is_dir($rootDir.'/vendor/symfony/lib/plugins/'.$name);
        }));

        $plugins = PluginLoader::filterPluginsByDependencies($plugins, $rootDir);

        return self::orderCoreFirst($plugins);
    }

    /**
     * What differs between the two, without changing anything.
     *
     * missing   - enabled in atom_plugin, not loading
     * stale     - loading, but disabled in atom_plugin
     * unmanaged - loading, with no atom_plugin row: reported, never removed
     */
    public static function drift(string $rootDir, bool $prune = false): array
    {
        $current = self::current();
        $desired = self::desired($rootDir, $prune);

        $known = DB::table('atom_plugin')->pluck('name')->all();

        $disabled = DB::table('atom_plugin')->where('is_enabled', 0)->pluck('name')->all();

        return [
            'missing' => array_values(array_diff($desired, $current)),
            'removed' => array_values(array_diff($current, $desired)),
            // Loading, and the table says off. Reported always, acted on only
            // with prune, because the table has been wrong about this in the
            // field and the failure mode is a working feature going dark.
            'contradicted' => array_values(array_intersect($current, $disabled)),
            'unmanaged' => array_values(array_filter($current, static function ($name) use ($known) {
                return 0 === strpos((string) $name, 'ahg') && !in_array($name, $known, true);
            })),
            'current' => $current,
            'desired' => $desired,
        ];
    }

    /**
     * Write the computed list back. Returns the same report drift() gives.
     */
    public static function sync(string $rootDir, bool $prune = false): array
    {
        $report = self::drift($rootDir, $prune);
        $row = self::settingRow();

        if (null === $row || $report['missing'] === [] && $report['removed'] === []) {
            $report['applied'] = false;

            return $report;
        }

        $value = serialize($report['desired']);

        // Every culture row, not just the active one. The value is a list of
        // plugin names rather than translatable text, and sfPluginAdminPlugin
        // reads the source culture - which is not necessarily the culture whose
        // row a given request would update.
        DB::table('setting_i18n')->where('id', $row[0])->update(['value' => $value]);

        $report['applied'] = true;

        return $report;
    }

    /**
     * ahgCorePlugin ahead of every other AHG plugin.
     *
     * Plugins initialise in this order, and ahgCorePlugin is what registers the
     * framework autoloader. One initialised before it references AtomFramework
     * classes that do not exist yet, which fatals during configuration and
     * returns an empty body with a 200 status for every page on the site.
     */
    public static function orderCoreFirst(array $plugins): array
    {
        $position = array_search('ahgCorePlugin', $plugins, true);

        if (false === $position) {
            return $plugins;
        }

        $firstAhg = null;
        foreach ($plugins as $index => $name) {
            if (0 === strpos((string) $name, 'ahg')) {
                $firstAhg = $index;

                break;
            }
        }

        if (null === $firstAhg || $firstAhg >= $position) {
            return $plugins;
        }

        unset($plugins[$position]);
        $plugins = array_values($plugins);
        array_splice($plugins, $firstAhg, 0, 'ahgCorePlugin');

        return $plugins;
    }
}
