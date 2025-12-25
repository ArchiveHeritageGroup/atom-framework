<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\MetadataExtraction\Repositories;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Metadata Repository.
 *
 * Handles storage and retrieval of extracted metadata.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class MetadataRepository
{
    /**
     * Get digital object by ID.
     */
    public function getDigitalObject(int $id): ?object
    {
        return DB::table('digital_object as do')
            ->join('object as o', 'do.id', '=', 'o.id')
            ->where('do.id', $id)
            ->select(
                'do.id',
                'do.information_object_id',
                'do.mime_type',
                'do.name',
                'do.path',
                'do.byte_size'
            )
            ->first();
    }

    /**
     * Save metadata to property table.
     */
    public function saveMetadata(int $objectId, string $name, string $value, string $scope = 'digital_object'): void
    {
        // Check if property already exists
        $existing = DB::table('property')
            ->where('object_id', $objectId)
            ->where('name', $name)
            ->where('scope', $scope)
            ->first();

        if ($existing) {
            // Update existing
            DB::table('property')
                ->where('id', $existing->id)
                ->update([
                    'value' => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            // Insert new
            DB::table('property')->insert([
                'object_id' => $objectId,
                'name' => $name,
                'value' => $value,
                'scope' => $scope,
                'source_culture' => 'en',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Get the inserted ID and create object record
            $propertyId = DB::getPdo()->lastInsertId();

            DB::table('object')->insert([
                'id' => $propertyId,
                'class_name' => 'QubitProperty',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('property_i18n')->insert([
                'id' => $propertyId,
                'culture' => 'en',
            ]);
        }
    }

    /**
     * Get metadata for object.
     */
    public function getMetadata(int $objectId, ?string $name = null): array
    {
        $query = DB::table('property')
            ->where('object_id', $objectId);

        if ($name) {
            $query->where('name', $name);
        }

        return $query->get()->toArray();
    }

    /**
     * Delete metadata.
     */
    public function deleteMetadata(int $objectId, ?string $name = null): void
    {
        $query = DB::table('property')
            ->where('object_id', $objectId);

        if ($name) {
            $query->where('name', $name);
        }

        $properties = $query->get();

        foreach ($properties as $property) {
            // Delete from property_i18n
            DB::table('property_i18n')->where('id', $property->id)->delete();

            // Delete from object
            DB::table('object')->where('id', $property->id)->delete();

            // Delete from property
            DB::table('property')->where('id', $property->id)->delete();
        }
    }
}
