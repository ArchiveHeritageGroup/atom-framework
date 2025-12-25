<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Helpers\CultureHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

require_once __DIR__ . "/SettingWrapper.php";
/**
 * Setting Service - Replaces QubitSetting (316 uses)
 */
class SettingService
{
    private static ?array $cache = null;
    private static string $culture = 'en';

    public static function getValue(string $name, ?string $culture = null): ?string
    {
        $setting = self::getByName($name, $culture);
        return $setting ? $setting->getValue() : null;
    }

    public static function getByName(string $name, ?string $culture = null): ?SettingWrapper
    {
        $culture = $culture ?? CultureHelper::getCulture();

        $setting = DB::table('setting as s')
            ->leftJoin('setting_i18n as si', fn($j) => $j->on('s.id', '=', 'si.id')->where('si.culture', $culture))
            ->where('s.name', $name)
            ->select('s.*', 'si.value', 'si.culture')
            ->first();

        if ($setting) {
            $setting->_value = $setting->value;
            return new SettingWrapper($setting);
        }

        return null;
    }


    public static function getByNameAndScope(string $name, string $scope, ?string $culture = null): ?SettingWrapper
    {
        $culture = $culture ?? CultureHelper::getCulture();

        $setting = DB::table('setting as s')
            ->leftJoin('setting_i18n as si', fn($j) => $j->on('s.id', '=', 'si.id')->where('si.culture', $culture))
            ->where('s.name', $name)
            ->where('s.scope', $scope)
            ->select('s.*', 'si.value', 'si.culture')
            ->first();

        if ($setting) {
            $setting->_value = $setting->value;
            return new SettingWrapper($setting);
        }

        return null;
    }

    public static function getByScope(string $scope, ?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();

        return DB::table('setting as s')
            ->leftJoin('setting_i18n as si', fn($j) => $j->on('s.id', '=', 'si.id')->where('si.culture', $culture))
            ->where('s.scope', $scope)
            ->select('s.*', 'si.value', 'si.culture')
            ->get()
            ->map(fn($s) => new SettingWrapper($s));
    }

    public static function getAll(?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();

        return DB::table('setting as s')
            ->leftJoin('setting_i18n as si', fn($j) => $j->on('s.id', '=', 'si.id')->where('si.culture', $culture))
            ->select('s.*', 'si.value', 'si.culture')
            ->get()
            ->map(fn($s) => new SettingWrapper($s));
    }

    public static function set(string $name, ?string $value, ?string $scope = null, ?string $culture = null): bool
    {
        $culture = $culture ?? CultureHelper::getCulture();

        $existing = $scope ? self::getByNameAndScope($name, $scope, $culture) : self::getByName($name, $culture);

        if ($existing) {
            // Update existing
            DB::table('setting_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->update(['value' => $value]);

            return true;
        }

        // Create new setting
        $id = DB::table('setting')->insertGetId([
            'name' => $name,
            'scope' => $scope,
            'editable' => 1,
            'deleteable' => 1,
            'source_culture' => $culture,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('setting_i18n')->insert([
            'id' => $id,
            'culture' => $culture,
            'value' => $value,
        ]);

        return true;
    }

    public static function delete(string $name, ?string $scope = null): bool
    {
        $query = DB::table('setting')->where('name', $name);

        if ($scope) {
            $query->where('scope', $scope);
        }

        $setting = $query->first();

        if ($setting) {
            DB::table('setting_i18n')->where('id', $setting->id)->delete();
            DB::table('setting')->where('id', $setting->id)->delete();
            return true;
        }

        return false;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
