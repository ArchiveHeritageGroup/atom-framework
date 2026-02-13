<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Digital object write operations.
 *
 * Uses Propel (QubitDigitalObject, QubitAsset) when available for
 * create operations (Symfony mode). Falls back to Laravel DB for
 * metadata updates and standalone mode.
 *
 * The create() method requires Propel because QubitDigitalObject::save()
 * triggers derivative generation, checksum computation, and storage
 * path resolution — all wired into the Propel model. A pure Laravel
 * replacement would need to replicate that logic.
 */
class PropelDigitalObjectWriteService implements DigitalObjectWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitDigitalObject', false)
            || class_exists('QubitDigitalObject');
    }

    public function create(int $objectId, string $filename, string $content, ?int $usageId = null): int
    {
        if (!$this->hasPropel) {
            throw new \RuntimeException(
                'Digital object creation requires Propel (QubitDigitalObject). '
                . 'This operation is not yet supported in standalone Heratio mode.'
            );
        }

        // Load the parent information object
        $resource = \QubitInformationObject::getById($objectId);
        if (null === $resource) {
            throw new \InvalidArgumentException("Information object #{$objectId} not found.");
        }

        $digitalObject = new \QubitDigitalObject();
        $digitalObject->assets[] = new \QubitAsset($filename, $content);
        $digitalObject->usageId = $usageId ?? \QubitTerm::MASTER_ID;

        // Attach to parent — Propel handles the relationship
        $resource->digitalObjectsRelatedByobjectId[] = $digitalObject;
        $resource->save();

        return $digitalObject->id;
    }

    public function updateMetadata(int $id, array $attributes): void
    {
        if (empty($attributes)) {
            return;
        }

        // Always use Laravel DB for metadata updates (works in both modes)
        $attributes['updated_at'] = date('Y-m-d H:i:s');
        DB::table('digital_object')->where('id', $id)->update($attributes);

        // Also update the parent object timestamp
        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function saveProperty(int $objectId, string $name, ?string $value, string $culture = 'en'): void
    {
        // Find existing property
        $property = DB::table('property')
            ->where('object_id', $objectId)
            ->where('name', $name)
            ->first();

        if (null === $value) {
            // Delete property if value is null
            if ($property) {
                DB::table('property_i18n')->where('id', $property->id)->delete();
                DB::table('property')->where('id', $property->id)->delete();
                DB::table('object')->where('id', $property->id)->delete();
            }

            return;
        }

        if ($property) {
            // Update existing
            DB::table('property_i18n')->updateOrInsert(
                ['id' => $property->id, 'culture' => $culture],
                ['value' => $value]
            );
        } else {
            // Create: object → property → property_i18n
            $propObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitProperty',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
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
        }
    }

    public function createDerivative(int $parentId, array $attributes): int
    {
        // Get the parent DO's information_object_id
        $parentDo = DB::table('digital_object')->where('id', $parentId)->first();
        if (!$parentDo) {
            throw new \InvalidArgumentException("Parent digital object #{$parentId} not found.");
        }

        // Create object row
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create digital_object row
        $doAttributes = array_merge([
            'id' => $objectId,
            'information_object_id' => $parentDo->information_object_id,
            'parent_id' => $parentId,
        ], $attributes);

        DB::table('digital_object')->insert($doAttributes);

        return $objectId;
    }

    public function delete(int $id): bool
    {
        if ($this->hasPropel) {
            $do = \QubitDigitalObject::getById($id);
            if (null === $do) {
                return false;
            }

            $do->delete();

            return true;
        }

        // Laravel DB fallback: delete derivatives first, then master
        $derivativeIds = DB::table('digital_object')
            ->where('parent_id', $id)
            ->pluck('id')
            ->toArray();

        foreach ($derivativeIds as $derivId) {
            DB::table('digital_object')->where('id', $derivId)->delete();
            DB::table('object')->where('id', $derivId)->delete();
        }

        DB::table('digital_object')->where('id', $id)->delete();
        DB::table('object')->where('id', $id)->delete();

        return true;
    }

    public function updateFileMetadata(int $id, array $metadata): void
    {
        $allowed = ['byte_size', 'mime_type', 'media_type_id', 'name', 'path', 'sequence', 'checksum', 'checksum_type'];
        $filtered = array_intersect_key($metadata, array_flip($allowed));

        if (empty($filtered)) {
            return;
        }

        $this->updateMetadata($id, $filtered);
    }
}
