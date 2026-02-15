<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone settings write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM setting storage pattern: object -> setting -> setting_i18n.
 */
class StandaloneSettingsWriteService implements SettingsWriteServiceInterface
{
    public function save(string $name, $value, ?string $scope = null): void
    {
        $culture = 'en';
        $existing = $this->findSetting($name, $scope);

        if ($existing) {
            DB::table('setting_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->update(['value' => $value]);
        } else {
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

    public function saveLocalized(string $name, $value, string $culture, ?string $scope = null): void
    {
        $existing = $this->findSetting($name, $scope);
        if (!$existing) {
            $this->save($name, $value, $scope);

            return;
        }

        DB::table('setting_i18n')->updateOrInsert(
            ['id' => $existing->id, 'culture' => $culture],
            ['value' => $value]
        );
    }

    public function saveSerialized(string $name, array $value, ?string $scope = null): void
    {
        $this->save($name, serialize($value), $scope);
    }

    public function delete(string $name, ?string $scope = null): bool
    {
        $existing = $this->findSetting($name, $scope);
        if (!$existing) {
            return false;
        }

        DB::table('setting_i18n')->where('id', $existing->id)->delete();
        DB::table('setting')->where('id', $existing->id)->delete();
        DB::table('object')->where('id', $existing->id)->delete();

        return true;
    }

    public function saveMany(array $settings, ?string $scope = null): void
    {
        foreach ($settings as $name => $value) {
            $this->save($name, $value, $scope);
        }
    }

    private function findSetting(string $name, ?string $scope): ?object
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
