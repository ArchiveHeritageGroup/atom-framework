<?php

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Custom field values as plain data, for exporters (#202): REST API, GraphQL,
 * CSV, EAD and the PDF finding aid.
 *
 * Custom fields are defined by ahgCustomFieldsPlugin (custom_field_definition /
 * custom_field_value). This reads them without loading that plugin, so an
 * exporter can call it whether or not the plugin is enabled: with no tables, or
 * no fields, every method returns empty.
 *
 * Values are text, ready to print: dropdown codes become their labels, booleans
 * "Yes"/"No", numbers lose trailing zeros. A repeatable field gives a list.
 */
class CustomFieldValues
{
    /** @var null|bool tables present, checked once per request */
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (null === self::$available) {
            try {
                self::$available = DB::schema()->hasTable('custom_field_definition') && DB::schema()->hasTable('custom_field_value');
            } catch (\Throwable $e) {
                self::$available = false;
            }
        }

        return self::$available;
    }

    /**
     * Active field definitions for an entity type, in display order.
     *
     * @param bool $publicOnly only fields marked visible to the public
     *
     * @return array<int, object> field_key, field_label, field_type, is_repeatable
     */
    public static function definitions(string $entityType, bool $publicOnly = false): array
    {
        if (!self::available()) {
            return [];
        }
        $q = DB::table('custom_field_definition')
            ->where('entity_type', $entityType)->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('id');
        if ($publicOnly) {
            $q->where('is_visible_public', 1);
        }

        return $q->get(['id', 'field_key', 'field_label', 'field_type', 'field_group', 'is_repeatable', 'dropdown_taxonomy'])->all();
    }

    /**
     * One record's values.
     *
     * @return array<string, array{label: string, type: string, group: ?string, values: string[]}> keyed by field_key
     */
    public static function forObject(int $objectId, string $entityType, bool $publicOnly = false): array
    {
        return self::forObjects([$objectId], $entityType, $publicOnly)[$objectId] ?? [];
    }

    /**
     * Values for many records in two queries, for list exports.
     *
     * @param int[] $objectIds
     *
     * @return array<int, array<string, array>> object id => field_key => field
     */
    public static function forObjects(array $objectIds, string $entityType, bool $publicOnly = false): array
    {
        $defs = self::definitions($entityType, $publicOnly);
        $objectIds = array_values(array_filter(array_map('intval', $objectIds)));
        if (!$defs || !$objectIds) {
            return [];
        }

        $byId = [];
        foreach ($defs as $def) {
            $byId[(int) $def->id] = $def;
        }

        $out = [];
        foreach (array_chunk($objectIds, 1000) as $chunk) {
            $rows = DB::table('custom_field_value')
                ->whereIn('object_id', $chunk)
                ->whereIn('field_definition_id', array_keys($byId))
                ->orderBy('object_id')->orderBy('field_definition_id')->orderBy('sequence')
                ->get();

            foreach ($rows as $row) {
                $def = $byId[(int) $row->field_definition_id];
                $value = self::text($def, $row);
                if (null === $value || '' === $value) {
                    continue;
                }
                $field = &$out[(int) $row->object_id][$def->field_key];
                if (null === $field) {
                    $field = ['label' => (string) $def->field_label, 'type' => (string) $def->field_type, 'group' => $def->field_group, 'values' => []];
                }
                $field['values'][] = $value;
                unset($field);
            }
        }

        // Keep the definition order inside each record.
        $order = array_flip(array_map(static fn ($d) => $d->field_key, $defs));
        foreach ($out as &$fields) {
            uksort($fields, static fn ($a, $b) => $order[$a] <=> $order[$b]);
        }

        return $out;
    }

    /**
     * Append one column per custom field to a CSV export, matched on the id in
     * its legacyId column. Columns are named customField_<key>; repeatable
     * values are joined with "|", as elsewhere in AtoM's CSV. Streams the file
     * in batches, so large exports stay cheap. A file with no legacyId column,
     * or no fields defined, is left as it is.
     *
     * @return int columns added
     */
    public static function appendToCsv(string $file, string $entityType, bool $publicOnly = false): int
    {
        $defs = self::definitions($entityType, $publicOnly);
        if (!$defs || !is_readable($file)) {
            return 0;
        }
        $in = fopen($file, 'rb');
        $header = fgetcsv($in);
        $idCol = false === $header ? false : array_search('legacyId', $header, true);
        if (false === $idCol) {
            fclose($in);

            return 0;
        }

        $tmp = $file.'.custom-fields.tmp';
        $out = fopen($tmp, 'wb');
        fputcsv($out, array_merge($header, array_map(static fn ($d) => 'customField_'.$d->field_key, $defs)));

        $flush = static function (array $rows) use ($out, $idCol, $entityType, $publicOnly, $defs) {
            $values = self::forObjects(array_map(static fn ($r) => (int) $r[$idCol], $rows), $entityType, $publicOnly);
            foreach ($rows as $row) {
                $fields = $values[(int) $row[$idCol]] ?? [];
                foreach ($defs as $def) {
                    $row[] = isset($fields[$def->field_key]) ? implode('|', $fields[$def->field_key]['values']) : '';
                }
                fputcsv($out, $row);
            }
        };

        $batch = [];
        while (false !== ($row = fgetcsv($in))) {
            $batch[] = $row;
            if (count($batch) >= 500) {
                $flush($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $flush($batch);
        }
        fclose($in);
        fclose($out);
        rename($tmp, $file);

        return count($defs);
    }

    /** One stored value as text. */
    private static function text(object $def, object $row): ?string
    {
        switch ($def->field_type) {
            case 'boolean':
                return null === $row->value_boolean ? null : ((int) $row->value_boolean ? 'Yes' : 'No');
            case 'number':
                return null === $row->value_number ? null : rtrim(rtrim(number_format((float) $row->value_number, 4, '.', ''), '0'), '.');
            case 'date':
                return null === $row->value_date ? null : (string) $row->value_date;
            case 'dropdown':
            case 'multiselect':
                return null === $row->value_dropdown ? null : self::dropdownLabel((string) $def->dropdown_taxonomy, (string) $row->value_dropdown);
            default:
                return null === $row->value_text ? null : (string) $row->value_text;
        }
    }

    private static function dropdownLabel(string $taxonomy, string $code): string
    {
        static $labels = [];
        $key = $taxonomy."\0".$code;
        if (!array_key_exists($key, $labels)) {
            try {
                $labels[$key] = (string) (DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('code', $code)->value('label') ?? $code);
            } catch (\Throwable $e) {
                $labels[$key] = $code;
            }
        }

        return $labels[$key];
    }
}
