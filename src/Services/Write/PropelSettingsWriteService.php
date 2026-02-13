<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Settings write operations via QubitSetting.
 *
 * Uses Propel (QubitSetting) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode.
 */
class PropelSettingsWriteService implements SettingsWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitSetting', false)
            || class_exists('QubitSetting');
    }

    public function save(string $name, $value, ?string $scope = null): void
    {
        if ($this->hasPropel) {
            $this->propelSave($name, $value, $scope);

            return;
        }

        $this->dbSave($name, $value, $scope);
    }

    public function saveLocalized(string $name, $value, string $culture, ?string $scope = null): void
    {
        if ($this->hasPropel) {
            $this->propelSaveLocalized($name, $value, $culture, $scope);

            return;
        }

        $this->dbSaveLocalized($name, $value, $culture, $scope);
    }

    public function saveSerialized(string $name, array $value, ?string $scope = null): void
    {
        $this->save($name, serialize($value), $scope);
    }

    public function delete(string $name, ?string $scope = null): bool
    {
        if ($this->hasPropel) {
            return $this->propelDelete($name, $scope);
        }

        return $this->dbDelete($name, $scope);
    }

    public function saveMany(array $settings, ?string $scope = null): void
    {
        foreach ($settings as $name => $value) {
            $this->save($name, $value, $scope);
        }
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelSave(string $name, $value, ?string $scope): void
    {
        $setting = $this->propelFindOrCreate($name, $scope);

        // Use setValue with sourceCulture flag for non-localized settings
        $setting->setValue($value, ['sourceCulture' => true]);
        $setting->save();
    }

    private function propelSaveLocalized(string $name, $value, string $culture, ?string $scope): void
    {
        $setting = $this->propelFindOrCreate($name, $scope);

        $setting->setValue($value, ['culture' => $culture]);
        $setting->save();
    }

    private function propelDelete(string $name, ?string $scope): bool
    {
        $setting = $this->propelFind($name, $scope);
        if (null === $setting) {
            return false;
        }

        $setting->delete();

        return true;
    }

    /**
     * Find an existing QubitSetting or create a new one.
     */
    private function propelFindOrCreate(string $name, ?string $scope): object
    {
        $setting = $this->propelFind($name, $scope);
        if (null !== $setting) {
            return $setting;
        }

        $setting = new \QubitSetting();
        $setting->name = $name;
        if (null !== $scope) {
            $setting->scope = $scope;
        }
        $setting->sourceCulture = \sfConfig::get('sf_default_culture', 'en');
        $setting->editable = true;
        $setting->deleteable = true;

        return $setting;
    }

    /**
     * Find a QubitSetting by name and scope.
     */
    private function propelFind(string $name, ?string $scope): ?object
    {
        // Use SettingService if available (AHG abstraction)
        if (class_exists('AtomExtensions\Services\SettingService', false)) {
            return \AtomExtensions\Services\SettingService::getByName($name, $scope);
        }

        // Direct Propel query
        $criteria = new \Criteria();
        $criteria->add(\QubitSetting::NAME, $name);
        if (null !== $scope) {
            $criteria->add(\QubitSetting::SCOPE, $scope);
        }

        return \QubitSetting::getOne($criteria);
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbSave(string $name, $value, ?string $scope): void
    {
        $culture = 'en';
        $existing = $this->dbFind($name, $scope);

        if ($existing) {
            DB::table('setting_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->update(['value' => $value]);
        } else {
            // Insert into object → setting → setting_i18n
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitSetting',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('setting')->insert([
                'id' => $objectId,
                'name' => $name,
                'scope' => $scope,
                'editable' => 1,
                'deleteable' => 1,
                'source_culture' => $culture,
            ]);

            DB::table('setting_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
                'value' => $value,
            ]);
        }
    }

    private function dbSaveLocalized(string $name, $value, string $culture, ?string $scope): void
    {
        $existing = $this->dbFind($name, $scope);
        if (!$existing) {
            // Create with source culture first, then add localized row
            $this->dbSave($name, $value, $scope);

            return;
        }

        DB::table('setting_i18n')->updateOrInsert(
            ['id' => $existing->id, 'culture' => $culture],
            ['value' => $value]
        );
    }

    private function dbDelete(string $name, ?string $scope): bool
    {
        $existing = $this->dbFind($name, $scope);
        if (!$existing) {
            return false;
        }

        DB::table('setting_i18n')->where('id', $existing->id)->delete();
        DB::table('setting')->where('id', $existing->id)->delete();
        DB::table('object')->where('id', $existing->id)->delete();

        return true;
    }

    private function dbFind(string $name, ?string $scope): ?object
    {
        $query = DB::table('setting')->where('name', $name);
        if (null !== $scope) {
            $query->where('scope', $scope);
        } else {
            $query->whereNull('scope');
        }

        return $query->first();
    }
}
