<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Http\Request;

/**
 * Load application settings from the database.
 *
 * Replaces QubitSettingsFilter. Queries the setting + setting_i18n tables
 * via Laravel QB and populates ConfigService for the request lifecycle.
 */
class LoadSettingsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $culture = ConfigService::get('culture', 'en');

        $settings = $this->loadSettings($culture);

        // Check environment variables (same logic as QubitSettingsFilter)
        $envHashmap = ['ATOM_READ_ONLY' => 'boolean'];
        foreach ($envHashmap as $env => $type) {
            $value = getenv($env);
            if (false === $value) {
                continue;
            }

            if ('boolean' === $type) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $key = strtolower(str_replace('ATOM', 'app', $env));
            $settings[$key] = $value;
        }

        // Populate ConfigService with all loaded settings
        foreach ($settings as $key => $value) {
            ConfigService::set($key, $value);
        }

        return $next($request);
    }

    /**
     * Load settings from database, replicating QubitSetting::getSettingsArray().
     */
    private function loadSettings(string $culture): array
    {
        $settings = [];
        $i18nLanguages = [];

        try {
            $rows = DB::table('setting')
                ->leftJoin('setting_i18n as current', function ($join) use ($culture) {
                    $join->on('setting.id', '=', 'current.id')
                        ->where('current.culture', '=', $culture);
                })
                ->leftJoin('setting_i18n as source', function ($join) {
                    $join->on('setting.id', '=', 'source.id')
                        ->whereColumn('source.culture', '=', 'setting.source_culture');
                })
                ->select([
                    'setting.name',
                    'setting.scope',
                    DB::raw('CASE WHEN (current.value IS NOT NULL AND current.value <> "") THEN current.value ELSE source.value END AS value'),
                    DB::raw('source.value AS value_source'),
                ])
                ->get();
        } catch (\Exception $e) {
            // If settings table doesn't exist yet, return empty
            return [];
        }

        foreach ($rows as $row) {
            if ($row->scope) {
                // Collect enabled languages into a single setting
                if ('i18n_languages' === $row->scope) {
                    $i18nLanguages[] = $row->value_source;

                    continue;
                }

                $key = 'app_' . $row->scope . '_' . $row->name;
            } else {
                $key = 'app_' . $row->name;
            }

            $settings[$key] = $row->value;
            $settings[$key . '__source'] = $row->value_source;
        }

        $settings['app_i18n_languages'] = $i18nLanguages;

        return $settings;
    }
}
