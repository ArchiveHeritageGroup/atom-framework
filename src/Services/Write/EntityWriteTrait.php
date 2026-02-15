<?php

namespace AtomFramework\Services\Write;

use AtomExtensions\Services\SlugService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Shared trait for standalone WriteService implementations.
 *
 * Extracts the common 3-table entity write pattern (object -> entity -> entity_i18n)
 * used by all AtoM entities. Provides transaction-wrapped INSERTs, slug generation,
 * and helper methods for related records (relations, notes, properties, events).
 */
trait EntityWriteTrait
{
    /**
     * Insert a new entity with the AtoM inheritance chain: object -> entity -> entity_i18n.
     *
     * @param string      $className Propel class name (e.g., 'QubitTerm')
     * @param string      $table     Entity table name (e.g., 'term')
     * @param array       $coreData  Core entity columns (excluding id and source_culture)
     * @param string|null $i18nTable I18n table name (null if no i18n)
     * @param array       $i18nData  I18n columns (excluding id and culture)
     * @param string      $culture   Culture code
     *
     * @return int The new object ID
     */
    protected function insertEntity(
        string $className,
        string $table,
        array $coreData,
        ?string $i18nTable,
        array $i18nData,
        string $culture
    ): int {
        return DB::transaction(function () use ($className, $table, $coreData, $i18nTable, $i18nData, $culture) {
            $now = date('Y-m-d H:i:s');

            // Step 1: INSERT into object table
            $objectId = DB::table('object')->insertGetId([
                'class_name' => $className,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Step 2: INSERT into entity table
            $coreData['id'] = $objectId;
            if (!isset($coreData['source_culture'])) {
                $coreData['source_culture'] = $culture;
            }
            DB::table($table)->insert($coreData);

            // Step 3: INSERT into entity_i18n table
            if (null !== $i18nTable) {
                $i18nData['id'] = $objectId;
                $i18nData['culture'] = $culture;
                DB::table($i18nTable)->insert($i18nData);
            }

            return $objectId;
        });
    }

    /**
     * Update an existing entity (entity table + i18n table + object.updated_at).
     */
    protected function updateEntity(
        int $id,
        string $table,
        array $coreData,
        ?string $i18nTable,
        array $i18nData,
        string $culture
    ): void {
        if (!empty($coreData)) {
            DB::table($table)->where('id', $id)->update($coreData);
        }

        if (null !== $i18nTable && !empty($i18nData)) {
            DB::table($i18nTable)
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nData);
        }

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete an entity: i18n -> entity -> object (reverse insert order).
     */
    protected function deleteEntity(int $id, string $table, ?string $i18nTable = null): bool
    {
        $exists = DB::table($table)->where('id', $id)->exists();
        if (!$exists) {
            return false;
        }

        return DB::transaction(function () use ($id, $table, $i18nTable) {
            if (null !== $i18nTable) {
                DB::table($i18nTable)->where('id', $id)->delete();
            }
            DB::table($table)->where('id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();

            return true;
        });
    }

    /**
     * Convert camelCase to snake_case.
     */
    protected function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }

    /**
     * Split data array into core fields and i18n fields.
     *
     * @param array $data          Input data (may have camelCase or snake_case keys)
     * @param array $i18nFieldList List of snake_case i18n field names
     *
     * @return array [coreData, i18nData] with all keys in snake_case
     */
    protected function splitI18nFields(array $data, array $i18nFieldList): array
    {
        $core = [];
        $i18n = [];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $i18nFieldList, true)) {
                $i18n[$snakeKey] = $value;
            } else {
                $core[$snakeKey] = $value;
            }
        }

        return [$core, $i18n];
    }

    /**
     * Generate and insert a slug for a new object.
     *
     * @param int    $objectId  The object ID
     * @param array  $data      Data array to extract name from
     * @param string $nameField The field to use for slug basis (snake_case)
     */
    protected function autoSlug(int $objectId, array $data, string $nameField = 'name'): void
    {
        $name = $data[$nameField] ?? null;
        if (null !== $name && '' !== $name) {
            SlugService::createSlug($objectId, $name);
        }
    }

    /**
     * Create a relation record: object -> relation.
     */
    protected function createRelationRecord(int $subjectId, int $objectId, int $typeId): int
    {
        $now = date('Y-m-d H:i:s');

        $relObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRelation',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('relation')->insert([
            'id' => $relObjectId,
            'subject_id' => $subjectId,
            'object_id' => $objectId,
            'type_id' => $typeId,
        ]);

        return $relObjectId;
    }

    /**
     * Create a note record: object -> note -> note_i18n.
     */
    protected function createNoteRecord(
        int $objectId,
        int $typeId,
        string $content,
        string $culture = 'en',
        string $scope = 'QubitTerm'
    ): int {
        $now = date('Y-m-d H:i:s');

        $noteObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitNote',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('note')->insert([
            'id' => $noteObjectId,
            'object_id' => $objectId,
            'type_id' => $typeId,
            'scope' => $scope,
            'source_culture' => $culture,
        ]);

        DB::table('note_i18n')->insert([
            'id' => $noteObjectId,
            'culture' => $culture,
            'content' => $content,
        ]);

        return $noteObjectId;
    }

    /**
     * Create a property record: object -> property -> property_i18n.
     */
    protected function createPropertyRecord(
        int $objectId,
        string $name,
        string $value,
        string $culture = 'en'
    ): int {
        $now = date('Y-m-d H:i:s');

        $propObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitProperty',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('property')->insert([
            'id' => $propObjectId,
            'object_id' => $objectId,
            'name' => $name,
            'source_culture' => $culture,
        ]);

        DB::table('property_i18n')->insert([
            'id' => $propObjectId,
            'culture' => $culture,
            'value' => $value,
        ]);

        return $propObjectId;
    }

    /**
     * Create an other-name record: object -> other_name -> other_name_i18n.
     */
    protected function createOtherNameRecord(
        int $objectId,
        string $name,
        int $typeId,
        string $culture = 'en'
    ): int {
        $now = date('Y-m-d H:i:s');

        $onObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitOtherName',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('other_name')->insert([
            'id' => $onObjectId,
            'object_id' => $objectId,
            'type_id' => $typeId,
            'source_culture' => $culture,
        ]);

        DB::table('other_name_i18n')->insert([
            'id' => $onObjectId,
            'culture' => $culture,
            'name' => $name,
        ]);

        return $onObjectId;
    }

    /**
     * Create an event record: object -> event -> event_i18n.
     */
    protected function createEventRecord(
        int $objectId,
        array $attributes,
        string $culture = 'en'
    ): int {
        $i18nFields = ['date', 'description'];
        [$coreData, $i18nData] = $this->splitI18nFields($attributes, $i18nFields);

        $now = date('Y-m-d H:i:s');

        $eventObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitEvent',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $coreData['id'] = $eventObjectId;
        $coreData['object_id'] = $objectId;
        if (!isset($coreData['source_culture'])) {
            $coreData['source_culture'] = $culture;
        }
        DB::table('event')->insert($coreData);

        $i18nData['id'] = $eventObjectId;
        $i18nData['culture'] = $culture;
        DB::table('event_i18n')->insert($i18nData);

        return $eventObjectId;
    }
}
