<?php

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Repository for Item-level Physical Location data
 */
class ItemPhysicalLocationRepository
{
    /**
     * Get physical location data for an information object
     */
    public function getLocationData(int $informationObjectId): ?array
    {
        $row = DB::table('information_object_physical_location')
            ->where('information_object_id', $informationObjectId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Save physical location data for an information object
     */
    public function saveLocationData(int $informationObjectId, array $data): int
    {
        $data['information_object_id'] = $informationObjectId;
        $data['updated_at'] = date('Y-m-d H:i:s');
        unset($data['id']);

        $existing = DB::table('information_object_physical_location')
            ->where('information_object_id', $informationObjectId)
            ->first();

        if ($existing) {
            DB::table('information_object_physical_location')
                ->where('id', $existing->id)
                ->update($data);
            return (int) $existing->id;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            return (int) DB::table('information_object_physical_location')->insertGetId($data);
        }
    }

    /**
     * Delete location data
     */
    public function deleteLocationData(int $informationObjectId): bool
    {
        return DB::table('information_object_physical_location')
            ->where('information_object_id', $informationObjectId)
            ->delete() > 0;
    }

    /**
     * Update access status
     */
    public function updateAccessStatus(int $informationObjectId, string $status, string $accessedBy = null): bool
    {
        $update = [
            'access_status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'in_use') {
            $update['last_accessed_at'] = date('Y-m-d H:i:s');
            $update['accessed_by'] = $accessedBy;
        }

        return DB::table('information_object_physical_location')
            ->where('information_object_id', $informationObjectId)
            ->update($update) > 0;
    }

    /**
     * Get items by physical object (container)
     */
    public function getItemsByContainer(int $physicalObjectId): array
    {
        return DB::table('information_object_physical_location as ipl')
            ->join('information_object as io', 'io.id', '=', 'ipl.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                     ->where('ioi.culture', '=', \AtomExtensions\Helpers\CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('ipl.physical_object_id', $physicalObjectId)
            ->select([
                'io.id',
                'ioi.title',
                's.slug',
                'ipl.*'
            ])
            ->orderBy('ipl.shelf')
            ->orderBy('ipl.row')
            ->orderBy('ipl.position')
            ->get()
            ->toArray();
    }

    /**
     * Get items by access status
     */
    public function getItemsByAccessStatus(string $status): array
    {
        return DB::table('information_object_physical_location as ipl')
            ->join('information_object as io', 'io.id', '=', 'ipl.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                     ->where('ioi.culture', '=', \AtomExtensions\Helpers\CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('ipl.access_status', $status)
            ->select([
                'io.id',
                'ioi.title',
                's.slug',
                'ipl.*'
            ])
            ->get()
            ->toArray();
    }

    /**
     * Get items by condition
     */
    public function getItemsByCondition(string $condition): array
    {
        return DB::table('information_object_physical_location as ipl')
            ->join('information_object as io', 'io.id', '=', 'ipl.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                     ->where('ioi.culture', '=', \AtomExtensions\Helpers\CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('ipl.condition_status', $condition)
            ->select([
                'io.id',
                'ioi.title',
                's.slug',
                'ipl.*'
            ])
            ->get()
            ->toArray();
    }

    /**
     * Find item by barcode
     */
    public function findByBarcode(string $barcode): ?array
    {
        $row = DB::table('information_object_physical_location as ipl')
            ->join('information_object as io', 'io.id', '=', 'ipl.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                     ->where('ioi.culture', '=', \AtomExtensions\Helpers\CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('ipl.barcode', $barcode)
            ->select([
                'io.id',
                'ioi.title',
                's.slug',
                'ipl.*'
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Get full location string for an item
     */
    public function getFullLocationString(int $informationObjectId): string
    {
        $data = $this->getLocationData($informationObjectId);
        if (!$data) {
            return '';
        }

        $parts = array_filter([
            !empty($data['box_number']) ? 'Box ' . $data['box_number'] : null,
            !empty($data['folder_number']) ? 'Folder ' . $data['folder_number'] : null,
            !empty($data['shelf']) ? 'Shelf ' . $data['shelf'] : null,
            !empty($data['row']) ? 'Row ' . $data['row'] : null,
            !empty($data['position']) ? 'Pos ' . $data['position'] : null,
            !empty($data['item_number']) ? 'Item ' . $data['item_number'] : null,
        ]);

        return implode(' > ', $parts);
    }

    /**
     * Get location with container details
     */
    public function getLocationWithContainer(int $informationObjectId): ?array
    {
        $location = $this->getLocationData($informationObjectId);
        if (!$location) {
            return null;
        }

        // Get container details if linked
        if (!empty($location['physical_object_id'])) {
            $container = DB::table('physical_object as po')
                ->leftJoin('physical_object_i18n as poi', function ($join) {
                    $join->on('poi.id', '=', 'po.id')
                         ->where('poi.culture', '=', \AtomExtensions\Helpers\CultureHelper::getCulture());
                })
                ->leftJoin('physical_object_extended as poe', 'poe.physical_object_id', '=', 'po.id')
                ->leftJoin('slug as s', 's.object_id', '=', 'po.id')
                ->where('po.id', $location['physical_object_id'])
                ->select([
                    'po.id',
                    'poi.name',
                    'poi.location',
                    's.slug',
                    'poe.*'
                ])
                ->first();

            $location['container'] = $container ? (array) $container : null;
        }

        return $location;
    }
}
