<?php

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Repository for Extended Physical Object data
 */
class PhysicalObjectExtendedRepository
{
    /**
     * physical_object_extended is created by ahgStorageManagePlugin, which is
     * OPTIONAL. The framework is always loaded, so every method here has to cope
     * with the table being absent - otherwise an install without that plugin
     * takes a hard 500 on /physicalobject/add, which is what archaeology was
     * doing. An absent table means "extended storage is not installed", which is
     * a truthful answer rather than a swallowed fault.
     */
    private static function tableExists(): bool
    {
        static $exists = null;

        if (null === $exists) {
            try {
                $exists = DB::schema()->hasTable('physical_object_extended');
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * A write that cannot happen must not look like one that did. Reads can
     * answer "nothing"; writes say so in the log, once per method, so a
     * half-configured install is diagnosable instead of silently lossy.
     */
    private static function unavailable(string $method): void
    {
        static $warned = [];

        if (!isset($warned[$method])) {
            $warned[$method] = true;
            error_log('PhysicalObjectExtendedRepository::' . $method
                . '() called but physical_object_extended is absent'
                . ' - ahgStorageManagePlugin is not installed on this instance.');
        }
    }

    /**
     * Get extended data for a physical object
     */
    public function getExtendedData(int $physicalObjectId): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $row = DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Save extended data for a physical object
     */
    public function saveExtendedData(int $physicalObjectId, array $data): int
    {
        if (!self::tableExists()) {
            self::unavailable('saveExtendedData');

            return 0;
        }

        $data['physical_object_id'] = $physicalObjectId;
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Remove generated columns (MySQL handles these)
        unset($data['available_capacity']);
        unset($data['available_linear_metres']);
        unset($data['id']);

        $existing = DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->first();

        if ($existing) {
            DB::table('physical_object_extended')
                ->where('id', $existing->id)
                ->update($data);
            return (int) $existing->id;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            return (int) DB::table('physical_object_extended')->insertGetId($data);
        }
    }

    /**
     * Update capacity usage
     */
    public function updateCapacityUsage(int $physicalObjectId, int $usedCapacity, float $usedLinearMetres = null): bool
    {
        if (!self::tableExists()) {
            self::unavailable('updateCapacityUsage');

            return false;
        }

        $update = ['used_capacity' => $usedCapacity];
        if ($usedLinearMetres !== null) {
            $update['used_linear_metres'] = $usedLinearMetres;
        }

        return DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->update($update) > 0;
    }

    /**
     * Increment capacity usage
     */
    public function incrementUsage(int $physicalObjectId, int $count = 1, float $linearMetres = 0): bool
    {
        if (!self::tableExists()) {
            self::unavailable('incrementUsage');

            return false;
        }

        return DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->update([
                'used_capacity' => DB::raw("used_capacity + {$count}"),
                'used_linear_metres' => DB::raw("used_linear_metres + {$linearMetres}"),
            ]) > 0;
    }

    /**
     * Decrement capacity usage
     */
    public function decrementUsage(int $physicalObjectId, int $count = 1, float $linearMetres = 0): bool
    {
        if (!self::tableExists()) {
            self::unavailable('decrementUsage');

            return false;
        }

        return DB::table('physical_object_extended')
            ->where('physical_object_id', $physicalObjectId)
            ->update([
                'used_capacity' => DB::raw("GREATEST(0, used_capacity - {$count})"),
                'used_linear_metres' => DB::raw("GREATEST(0, used_linear_metres - {$linearMetres})"),
            ]) > 0;
    }

    /**
     * Find locations with available capacity
     */
    public function findAvailableLocations(int $minCapacity = 1, string $building = null): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $query = DB::table('physical_object_extended as poe')
            ->join('physical_object as po', 'po.id', '=', 'poe.physical_object_id')
            ->leftJoin('physical_object_i18n as poi', function ($join) {
                $join->on('poi.id', '=', 'po.id')
                     ->where('poi.culture', '=', \AtomExtensions\Helpers\CultureHelper::getCulture());
            })
            ->where('poe.status', 'active')
            ->where('poe.available_capacity', '>=', $minCapacity)
            ->select([
                'po.id',
                'poi.name',
                'poi.location',
                'poe.*'
            ])
            ->orderBy('poe.building')
            ->orderBy('poe.room')
            ->orderBy('poe.rack')
            ->orderBy('poe.shelf');

        if ($building) {
            $query->where('poe.building', $building);
        }

        return $query->get()->toArray();
    }

    /**
     * Get capacity summary by building
     */
    public function getCapacitySummaryByBuilding(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return DB::table('physical_object_extended')
            ->select([
                'building',
                DB::raw('COUNT(*) as location_count'),
                DB::raw('SUM(total_capacity) as total_capacity'),
                DB::raw('SUM(used_capacity) as used_capacity'),
                DB::raw('SUM(available_capacity) as available_capacity'),
                DB::raw('SUM(total_linear_metres) as total_linear_metres'),
                DB::raw('SUM(used_linear_metres) as used_linear_metres'),
                DB::raw('SUM(available_linear_metres) as available_linear_metres'),
            ])
            ->where('status', 'active')
            ->groupBy('building')
            ->orderBy('building')
            ->get()
            ->toArray();
    }

    /**
     * Get full location string
     */
    public function getFullLocationString(int $physicalObjectId): string
    {
        $data = $this->getExtendedData($physicalObjectId);
        if (!$data) {
            return '';
        }

        $parts = array_filter([
            $data['building'] ?? null,
            $data['floor'] ? 'Floor ' . $data['floor'] : null,
            $data['room'] ? 'Room ' . $data['room'] : null,
            $data['aisle'] ? 'Aisle ' . $data['aisle'] : null,
            $data['bay'] ? 'Bay ' . $data['bay'] : null,
            $data['rack'] ? 'Rack ' . $data['rack'] : null,
            $data['shelf'] ? 'Shelf ' . $data['shelf'] : null,
            $data['position'] ? 'Pos ' . $data['position'] : null,
        ]);

        return implode(' > ', $parts);
    }

    /**
     * Get related information objects for a physical object
     */
    public function getRelatedResources(int $physicalObjectId): array
    {
        return DB::table('relation as r')
            ->join('information_object as io', 'io.id', '=', 'r.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'io.id')
                     ->where('ioi.culture', '=', \AtomExtensions\Helpers\CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('r.subject_id', $physicalObjectId)
            ->where('r.type_id', \QubitTerm::HAS_PHYSICAL_OBJECT_ID)
            ->select([
                'io.id',
                'ioi.title',
                's.slug'
            ])
            ->get()
            ->toArray();
    }
}
