<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Access;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Embargo Service.
 *
 * Manages embargoes on objects.
 */
class EmbargoService
{
    /**
     * Check if object is embargoed.
     */
    public function isEmbargoed(int $objectId): bool
    {
        $today = date('Y-m-d');

        return DB::table('heritage_embargo')
            ->where('object_id', $objectId)
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->exists();
    }

    /**
     * Get embargo for object.
     */
    public function getEmbargo(int $objectId): ?object
    {
        $today = date('Y-m-d');

        return DB::table('heritage_embargo')
            ->where('object_id', $objectId)
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->first();
    }

    /**
     * Get all active embargoes.
     */
    public function getActiveEmbargoes(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;
        $today = date('Y-m-d');

        $query = DB::table('heritage_embargo')
            ->leftJoin('information_object', 'heritage_embargo.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->select([
                'heritage_embargo.*',
                'information_object.slug',
                'information_object_i18n.title',
            ])
            ->where(function ($q) use ($today) {
                $q->whereNull('heritage_embargo.start_date')
                    ->orWhere('heritage_embargo.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('heritage_embargo.end_date')
                    ->orWhere('heritage_embargo.end_date', '>=', $today);
            });

        $total = $query->count();

        $embargoes = $query->orderBy('heritage_embargo.end_date')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'embargoes' => $embargoes,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get expiring embargoes.
     */
    public function getExpiringEmbargoes(int $days = 30): Collection
    {
        $today = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime("+{$days} days"));

        return DB::table('heritage_embargo')
            ->leftJoin('information_object', 'heritage_embargo.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->select([
                'heritage_embargo.*',
                'information_object.slug',
                'information_object_i18n.title',
            ])
            ->whereNotNull('heritage_embargo.end_date')
            ->where('heritage_embargo.end_date', '>=', $today)
            ->where('heritage_embargo.end_date', '<=', $futureDate)
            ->orderBy('heritage_embargo.end_date')
            ->get();
    }

    /**
     * Create embargo.
     */
    public function create(array $data): int
    {
        return (int) DB::table('heritage_embargo')->insertGetId([
            'object_id' => $data['object_id'],
            'embargo_type' => $data['embargo_type'] ?? 'full',
            'reason' => $data['reason'] ?? null,
            'legal_basis' => $data['legal_basis'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'auto_release' => $data['auto_release'] ?? 1,
            'notify_on_release' => $data['notify_on_release'] ?? 1,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update embargo.
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'embargo_type',
            'reason',
            'legal_basis',
            'start_date',
            'end_date',
            'auto_release',
            'notify_on_release',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_embargo')
            ->where('id', $id)
            ->update($updateData) >= 0;
    }

    /**
     * Remove embargo.
     */
    public function remove(int $id): bool
    {
        return DB::table('heritage_embargo')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Process auto-releasing expired embargoes.
     */
    public function processExpiredEmbargoes(): int
    {
        $today = date('Y-m-d');

        return DB::table('heritage_embargo')
            ->where('auto_release', 1)
            ->where('end_date', '<', $today)
            ->delete();
    }

    /**
     * Get embargo statistics.
     */
    public function getStats(): array
    {
        $today = date('Y-m-d');

        $active = DB::table('heritage_embargo')
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->count();

        $expiringThisMonth = DB::table('heritage_embargo')
            ->whereNotNull('end_date')
            ->where('end_date', '>=', $today)
            ->where('end_date', '<=', date('Y-m-d', strtotime('+30 days')))
            ->count();

        $byType = DB::table('heritage_embargo')
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->select('embargo_type', DB::raw('COUNT(*) as count'))
            ->groupBy('embargo_type')
            ->pluck('count', 'embargo_type')
            ->toArray();

        return [
            'active' => $active,
            'expiring_soon' => $expiringThisMonth,
            'by_type' => $byType,
        ];
    }
}
