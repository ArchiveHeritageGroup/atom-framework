<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Show or set AtoM settings.
 *
 * Reads from and writes to the setting / setting_i18n tables.
 *
 * Usage:
 *   php bin/atom tools:settings                   # List all settings
 *   php bin/atom tools:settings --name=site_title  # Show a specific setting
 *   php bin/atom tools:settings --name=site_title --value="My Archive"  # Set a value
 */
class SettingsCommand extends BaseCommand
{
    protected string $name = 'tools:settings';
    protected string $description = 'Show or set AtoM settings';

    protected function configure(): void
    {
        $this->addOption('name', null, 'Setting name to show or set');
        $this->addOption('value', null, 'New value (requires --name)');
        $this->addOption('culture', null, 'Culture code for i18n settings', 'en');
        $this->addOption('scope', null, 'Setting scope filter');
    }

    protected function handle(): int
    {
        $name = $this->option('name');
        $value = $this->option('value');
        $culture = $this->option('culture') ?: 'en';

        // Set a specific setting
        if ($name && $value !== null) {
            return $this->setSetting($name, $value, $culture);
        }

        // Show a specific setting
        if ($name) {
            return $this->showSetting($name, $culture);
        }

        // List all settings
        return $this->listSettings($culture);
    }

    private function setSetting(string $name, string $value, string $culture): int
    {
        // Find the setting by name
        $setting = DB::table('setting')->where('name', $name)->first();

        if (!$setting) {
            $this->error("Setting '{$name}' not found.");
            return 1;
        }

        // Update or insert the i18n value
        $existing = DB::table('setting_i18n')
            ->where('id', $setting->id)
            ->where('culture', $culture)
            ->first();

        if ($existing) {
            DB::table('setting_i18n')
                ->where('id', $setting->id)
                ->where('culture', $culture)
                ->update(['value' => $value]);
        } else {
            DB::table('setting_i18n')->insert([
                'id' => $setting->id,
                'culture' => $culture,
                'value' => $value,
            ]);
        }

        $this->success("Setting '{$name}' updated to: {$value}");

        return 0;
    }

    private function showSetting(string $name, string $culture): int
    {
        $result = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.name', $name)
            ->select('setting.id', 'setting.name', 'setting.scope', 'setting_i18n.value')
            ->first();

        if (!$result) {
            $this->error("Setting '{$name}' not found.");
            return 1;
        }

        $this->newline();
        $this->bold("  Setting: {$result->name}");
        $this->info("  Scope: " . ($result->scope ?: '(none)'));
        $this->info("  Value: " . ($result->value ?: '(empty)'));
        $this->newline();

        return 0;
    }

    private function listSettings(string $culture): int
    {
        $scope = $this->option('scope');

        $query = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->select('setting.id', 'setting.name', 'setting.scope', 'setting_i18n.value')
            ->orderBy('setting.scope')
            ->orderBy('setting.name');

        if ($scope) {
            $query->where('setting.scope', $scope);
        }

        $settings = $query->get();

        if ($settings->isEmpty()) {
            $this->info('No settings found.');
            return 0;
        }

        $rows = [];
        foreach ($settings as $s) {
            $displayValue = $s->value ?? '(empty)';
            // Truncate long values for display
            if (strlen($displayValue) > 60) {
                $displayValue = substr($displayValue, 0, 57) . '...';
            }
            $rows[] = [$s->id, $s->name, $s->scope ?: '(none)', $displayValue];
        }

        $this->newline();
        $this->table(['ID', 'Name', 'Scope', 'Value'], $rows);
        $this->newline();
        $this->info('  Total: ' . count($rows) . ' setting(s)');

        return 0;
    }
}
