<?php

declare(strict_types=1);

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Sector record write service - full CRUD for GLAM sector records.
 *
 * A sector record is a QubitInformationObject (source_standard = the sector) plus
 * the sector's primary extension data. The four supported sectors are non-uniform:
 *
 *   library  -> `library_item`     table, keyed by `information_object_id` (NO FK cascade)
 *   dam      -> `dam_iptc_metadata` table, keyed by `object_id`            (NO FK cascade)
 *   museum   -> `museum_metadata`   table, keyed by `object_id`            (FK ON DELETE CASCADE)
 *   gallery  -> `property` JSON blob name='galleryData'                    (property FK cascade)
 *
 * Create/update upsert the extension (there is no DB unique key on the library/dam
 * link columns, so we select-then-insert/update). Delete removes the non-cascading
 * extension rows explicitly, then deletes the information object (which cascades the
 * museum_metadata / galleryData property / display_object_config rows and the
 * nested-set links).
 *
 * This centralises write logic that previously lived inline in the sector edit
 * actions (saveLibraryItem / saveIptcMetadataDirectly / saveMuseumData / galleryData
 * saveProperty). Library/DAM plugins are locked, so this lives in the framework.
 *
 * @author The Archive and Heritage Group (Pty) Ltd
 */
class SectorRecordWriteService
{
    /** @var array<string,array<string,mixed>> per-sector storage config */
    private const SECTORS = [
        'library' => [
            'storage' => 'table', 'table' => 'library_item', 'key' => 'information_object_id',
            'defaults' => ['material_type' => 'monograph'], 'cascade' => false,
        ],
        'dam' => [
            'storage' => 'table', 'table' => 'dam_iptc_metadata', 'key' => 'object_id',
            'defaults' => [], 'cascade' => false,
            'extra_tables' => ['dam_external_links', 'dam_format_holdings', 'dam_version_links'],
        ],
        'museum' => [
            'storage' => 'table', 'table' => 'museum_metadata', 'key' => 'object_id',
            'defaults' => [], 'cascade' => true, 'json_fields' => ['materials', 'techniques'],
        ],
        'gallery' => [
            'storage' => 'property', 'property' => 'galleryData', 'cascade' => true,
        ],
    ];

    public static function supportedSectors(): array
    {
        return array_keys(self::SECTORS);
    }

    private function config(string $sector): array
    {
        $s = strtolower($sector);
        if (!isset(self::SECTORS[$s])) {
            throw new \InvalidArgumentException("Unknown GLAM sector '{$sector}'. Supported: ".implode(', ', self::supportedSectors()));
        }

        return self::SECTORS[$s] + ['sector' => $s];
    }

    /**
     * Create a sector record: information object (source_standard = sector) + extension.
     *
     * @param string $sector library|dam|museum|gallery
     * @param string $title  descriptive title
     * @param array  $fields sector extension fields (column => value, or JSON keys for gallery)
     *
     * @return int the new information_object id
     */
    public function create(string $sector, string $title, array $fields = [], string $culture = 'en'): int
    {
        $cfg = $this->config($sector);
        $ioId = (int) WriteServiceFactory::informationObject()
            ->createInformationObject(['title' => $title, 'source_standard' => $cfg['sector']], $culture);

        $this->writeExtension($cfg, $ioId, $fields, $culture);

        return $ioId;
    }

    /**
     * Update (upsert) the sector extension for an existing information object.
     */
    public function update(int $ioId, string $sector, array $fields, string $culture = 'en'): void
    {
        $this->writeExtension($this->config($sector), $ioId, $fields, $culture);
    }

    /**
     * Read the sector extension for an information object (assoc array, or null).
     */
    public function read(int $ioId, string $sector): ?array
    {
        $cfg = $this->config($sector);

        if ('property' === $cfg['storage']) {
            $prop = DB::table('property')->where('object_id', $ioId)->where('name', $cfg['property'])->first();
            if (!$prop) {
                return null;
            }
            $val = DB::table('property_i18n')->where('id', $prop->id)->value('value');

            return null === $val ? [] : (json_decode((string) $val, true) ?: []);
        }

        $row = DB::table($cfg['table'])->where($cfg['key'], $ioId)->first();

        return $row ? (array) $row : null;
    }

    /**
     * Delete a sector record: extension (explicit where not cascading) + information object.
     */
    public function delete(int $ioId, string $sector): void
    {
        $cfg = $this->config($sector);

        // Remove non-cascading extension rows explicitly (library_item / dam_* have no FK).
        if ('table' === $cfg['storage'] && empty($cfg['cascade'])) {
            DB::table($cfg['table'])->where($cfg['key'], $ioId)->delete();
            foreach (($cfg['extra_tables'] ?? []) as $t) {
                try { DB::table($t)->where('object_id', $ioId)->delete(); } catch (\Throwable $e) {}
            }
        }
        // display_object_config is keyed on object_id; it cascades on IO delete, but clear
        // it defensively in case referential integrity is not enforced on this deployment.
        try { DB::table('display_object_config')->where('object_id', $ioId)->delete(); } catch (\Throwable $e) {}

        // Delete the information object (cascades museum_metadata / galleryData property /
        // nested-set links via base AtoM).
        if (class_exists('\QubitInformationObject')) {
            $io = \QubitInformationObject::getById($ioId);
            if ($io) {
                $io->delete();

                return;
            }
        }
        // Fallback (no AtoM runtime): best-effort direct cleanup.
        foreach (['museum_metadata' => 'object_id'] as $t => $k) {
            try { DB::table($t)->where($k, $ioId)->delete(); } catch (\Throwable $e) {}
        }
        $props = DB::table('property')->where('object_id', $ioId)->pluck('id')->all();
        if ($props) {
            DB::table('property_i18n')->whereIn('id', $props)->delete();
            DB::table('property')->whereIn('id', $props)->delete();
        }
        DB::table('information_object_i18n')->where('id', $ioId)->delete();
        DB::table('information_object')->where('id', $ioId)->delete();
        DB::table('object')->where('id', $ioId)->delete();
    }

    // ---------------------------------------------------------------------

    private function writeExtension(array $cfg, int $ioId, array $fields, string $culture): void
    {
        if ('property' === $cfg['storage']) {
            $this->writeProperty($ioId, $cfg['property'], $fields, $culture);
            $this->upsertDisplayConfig($ioId, $cfg['sector']);

            return;
        }

        $table = $cfg['table'];
        $key = $cfg['key'];
        $data = ($cfg['defaults'] ?? []) + $fields;

        // JSON-encode array-valued fields (museum materials/techniques).
        foreach (($cfg['json_fields'] ?? []) as $jf) {
            if (isset($data[$jf]) && is_array($data[$jf])) {
                $data[$jf] = json_encode($data[$jf]);
            }
        }
        $data[$key] = $ioId;

        $schema = DB::schema();
        $now = date('Y-m-d H:i:s');
        if ($schema->hasColumn($table, 'updated_at')) {
            $data['updated_at'] = $now;
        }

        $exists = DB::table($table)->where($key, $ioId)->exists();
        if ($exists) {
            DB::table($table)->where($key, $ioId)->update($data);
        } else {
            if ($schema->hasColumn($table, 'created_at')) {
                $data['created_at'] = $now;
            }
            DB::table($table)->insert($data);
        }

        $this->upsertDisplayConfig($ioId, $cfg['sector']);
    }

    private function writeProperty(int $ioId, string $name, array $fields, string $culture): void
    {
        $json = json_encode($fields);
        $prop = DB::table('property')->where('object_id', $ioId)->where('name', $name)->first();
        if ($prop) {
            $has = DB::table('property_i18n')->where('id', $prop->id)->exists();
            $has
                ? DB::table('property_i18n')->where('id', $prop->id)->update(['value' => $json])
                : DB::table('property_i18n')->insert(['id' => $prop->id, 'culture' => $culture, 'value' => $json]);

            return;
        }
        $pid = DB::table('property')->insertGetId(['object_id' => $ioId, 'name' => $name, 'source_culture' => $culture]);
        DB::table('property_i18n')->insert(['id' => $pid, 'culture' => $culture, 'value' => $json]);
    }

    private function upsertDisplayConfig(int $ioId, string $sector): void
    {
        try {
            if (!DB::schema()->hasTable('display_object_config')) {
                return;
            }
            $exists = DB::table('display_object_config')->where('object_id', $ioId)->exists();
            $exists
                ? DB::table('display_object_config')->where('object_id', $ioId)->update(['object_type' => $sector])
                : DB::table('display_object_config')->insert(['object_id' => $ioId, 'object_type' => $sector]);
        } catch (\Throwable $e) {
            // display config is best-effort; never fail a write on it
        }
    }
}
