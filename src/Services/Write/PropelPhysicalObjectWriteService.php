<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: PhysicalObject write operations.
 *
 * Uses Propel (QubitPhysicalObject) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode.
 *
 * Entity inheritance chain:
 *   object -> physical_object -> physical_object_i18n
 */
class PropelPhysicalObjectWriteService implements PhysicalObjectWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitPhysicalObject', false)
            || class_exists('QubitPhysicalObject');
    }

    public function newPhysicalObject(): object
    {
        if ($this->hasPropel) {
            return new \QubitPhysicalObject();
        }

        return new \stdClass();
    }

    public function createPhysicalObject(array $data, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreatePhysicalObject($data, $culture);
        }

        return $this->dbCreatePhysicalObject($data, $culture);
    }

    public function updatePhysicalObject(int $id, array $data, string $culture = 'en'): void
    {
        if ($this->hasPropel) {
            $this->propelUpdatePhysicalObject($id, $data, $culture);

            return;
        }

        $this->dbUpdatePhysicalObject($id, $data, $culture);
    }

    public function savePhysicalObject(object $resource): int
    {
        if ($this->hasPropel && $resource instanceof \QubitPhysicalObject) {
            $resource->save();

            return (int) $resource->id;
        }

        // Standalone mode: extract properties and create/update
        if (isset($resource->id) && $resource->id) {
            $this->dbUpdateFromObject($resource);

            return (int) $resource->id;
        }

        return $this->dbCreateFromObject($resource);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreatePhysicalObject(array $data, string $culture): int
    {
        $po = new \QubitPhysicalObject();
        $po->sourceCulture = $culture;

        foreach ($data as $key => $value) {
            $po->{$key} = $value;
        }

        $po->save();

        return (int) $po->id;
    }

    private function propelUpdatePhysicalObject(int $id, array $data, string $culture): void
    {
        $po = \QubitPhysicalObject::getById($id);
        if (null === $po) {
            return;
        }

        foreach ($data as $key => $value) {
            $po->{$key} = $value;
        }

        $po->save();
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreatePhysicalObject(array $data, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        // Separate i18n fields from core fields
        $i18nFields = ['name', 'description', 'location'];
        $i18nData = [];
        $coreData = [];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $i18nFields)) {
                $i18nData[$snakeKey] = $value;
            } else {
                $coreData[$snakeKey] = $value;
            }
        }

        // 1. INSERT into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitPhysicalObject',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. INSERT into physical_object table
        $coreData['id'] = $objectId;
        $coreData['source_culture'] = $culture;
        DB::table('physical_object')->insert($coreData);

        // 3. INSERT i18n row
        if (!empty($i18nData)) {
            $i18nData['id'] = $objectId;
            $i18nData['culture'] = $culture;
            DB::table('physical_object_i18n')->insert($i18nData);
        }

        return $objectId;
    }

    private function dbUpdatePhysicalObject(int $id, array $data, string $culture): void
    {
        $i18nFields = ['name', 'description', 'location'];
        $i18nUpdates = [];
        $coreUpdates = [];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $i18nFields)) {
                $i18nUpdates[$snakeKey] = $value;
            } else {
                $coreUpdates[$snakeKey] = $value;
            }
        }

        if (!empty($coreUpdates)) {
            DB::table('physical_object')->where('id', $id)->update($coreUpdates);
        }

        if (!empty($i18nUpdates)) {
            DB::table('physical_object_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nUpdates);
        }

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create from a stdClass object (standalone mode).
     */
    private function dbCreateFromObject(object $resource): int
    {
        $data = [];
        foreach (get_object_vars($resource) as $key => $value) {
            if (null !== $value && 'id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        return $this->dbCreatePhysicalObject($data, $culture);
    }

    /**
     * Update from a stdClass object (standalone mode).
     */
    private function dbUpdateFromObject(object $resource): void
    {
        $data = [];
        foreach (get_object_vars($resource) as $key => $value) {
            if ('id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        $this->dbUpdatePhysicalObject((int) $resource->id, $data, $culture);
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }
}
