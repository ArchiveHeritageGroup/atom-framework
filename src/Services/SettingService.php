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
        $id = self::createSettingRow($name, $scope, $culture, 1);

        DB::table('setting_i18n')->insert([
            'id' => $id,
            'culture' => $culture,
            'value' => $value,
        ]);

        return true;
    }

    /**
     * Create the setting row and return its id.
     *
     * QubitSetting is object-derived: the id comes from the `object` table, and
     * `setting` merely reuses it. Letting `setting`'s own AUTO_INCREMENT allocate
     * instead produced "Duplicate entry for key setting.PRIMARY" - its counter sat
     * at 199 while ids in use ran past 5700, because every id ever assigned came
     * from `object`. The same insert also wrote created_at/updated_at, which
     * `setting` does not have.
     */
    private static function createSettingRow(string $name, ?string $scope, string $culture, int $editable): int
    {
        $id = DB::table('object')->insertGetId([
            'class_name' => 'QubitSetting',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table('setting')->insert([
            'id' => $id,
            'name' => $name,
            'scope' => $scope,
            'editable' => $editable,
            'deleteable' => 1,
            'source_culture' => $culture,
        ]);

        return (int) $id;
    }

    /**
     * Find a setting by name, create it when asked to, write the value, return it.
     *
     * The port of the settings screens from QubitSetting to this service kept the
     * call sites verbatim, but this method was never carried across, so every
     * handler that saved a setting this way fataled with "Call to undefined
     * method" the moment the form was submitted - the diacritics, template and
     * finding aid screens among them.
     *
     * Semantics follow QubitSetting::findAndSave so the existing call sites mean
     * what they say:
     *   scope         restrict the lookup, and set it on a newly created row
     *   createNew     create the setting when no row matches; without it a missing
     *                 setting returns null rather than being invented
     *   sourceCulture write against the row's own source_culture instead of the
     *                 viewer's culture, for settings that are not translated
     *   editable      set the editable flag while saving
     *
     * The i18n row is upserted rather than updated: a setting row can exist with
     * no translation for the culture being written, and an UPDATE there silently
     * matches nothing while still reporting success.
     */
    public static function findAndSave(string $name, $value, array $options = []): ?SettingWrapper
    {
        $scope = $options['scope'] ?? null;

        $query = DB::table('setting')->where('name', $name);

        if (null !== $scope) {
            $query->where('scope', $scope);
        }

        $row = $query->first();

        if (!$row && empty($options['createNew'])) {
            return null;
        }

        $culture = CultureHelper::getCulture();

        if (!$row) {
            $id = self::createSettingRow($name, $scope, $culture, (int) ($options['editable'] ?? 1));
        } else {
            $id = (int) $row->id;

            if (isset($options['editable'])) {
                DB::table('setting')->where('id', $id)->update(['editable' => (int) $options['editable']]);
            }

            if (!empty($options['sourceCulture']) && !empty($row->source_culture)) {
                $culture = (string) $row->source_culture;
            }
        }

        DB::table('setting_i18n')->updateOrInsert(
            ['id' => $id, 'culture' => $culture],
            ['value' => null === $value ? null : (string) $value]
        );

        self::clearCache();

        return self::getByName($name, $culture);
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
