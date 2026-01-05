<?php

namespace AtomExtensions\Services\Rights;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class EmbargoService
{
    public function getEmbargo(int $embargoId): ?object
    {
        return DB::table('embargo as e')
            ->leftJoin('embargo_i18n as ei', function ($join) {
                $join->on('ei.embargo_id', '=', 'e.id')
                    ->where('ei.culture', '=', 'en');
            })
            ->where('e.id', $embargoId)
            ->select(['e.*', 'ei.reason', 'ei.notes', 'ei.public_message'])
            ->first();
    }

    public function getObjectEmbargoes(int $objectId): Collection
    {
        return DB::table('embargo as e')
            ->leftJoin('embargo_i18n as ei', function ($join) {
                $join->on('ei.embargo_id', '=', 'e.id')
                    ->where('ei.culture', '=', 'en');
            })
            ->where('e.object_id', $objectId)
            ->orderByDesc('e.created_at')
            ->select(['e.*', 'ei.reason', 'ei.notes', 'ei.public_message'])
            ->get();
    }

    public function getActiveEmbargo(int $objectId): ?object
    {
        $now = date('Y-m-d H:i:s');
        return DB::table('embargo as e')
            ->leftJoin('embargo_i18n as ei', function ($join) {
                $join->on('ei.embargo_id', '=', 'e.id')
                    ->where('ei.culture', '=', 'en');
            })
            ->where('e.object_id', $objectId)
            ->where('e.status', 'active')
            ->where('e.start_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('e.end_date')
                    ->orWhere('e.end_date', '>=', $now);
            })
            ->select(['e.*', 'ei.reason', 'ei.public_message'])
            ->first();
    }

    public function getActiveEmbargoes(): Collection
    {
        $now = date('Y-m-d H:i:s');
        return DB::table('embargo as e')
            ->leftJoin('embargo_i18n as ei', function ($join) {
                $join->on('ei.embargo_id', '=', 'e.id')
                    ->where('ei.culture', '=', 'en');
            })
            ->leftJoin('information_object as io', 'e.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('e.status', 'active')
            ->where('e.start_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('e.end_date')
                    ->orWhere('e.end_date', '>=', $now);
            })
            ->select(['e.*', 'ei.reason', 'ei.public_message', 'ioi.title as object_title', 'slug.slug as object_slug'])
            ->orderByDesc('e.created_at')
            ->get();
    }

    public function getExpiringEmbargoes(int $days = 30): Collection
    {
        $now = date('Y-m-d');
        $future = date('Y-m-d', strtotime("+{$days} days"));
        
        return DB::table('embargo as e')
            ->leftJoin('embargo_i18n as ei', function ($join) {
                $join->on('ei.embargo_id', '=', 'e.id')
                    ->where('ei.culture', '=', 'en');
            })
            ->leftJoin('information_object as io', 'e.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('e.status', 'active')
            ->where('e.is_perpetual', false)
            ->whereNotNull('e.end_date')
            ->whereBetween('e.end_date', [$now, $future])
            ->select(['e.*', 'ei.reason', 'ioi.title as object_title', 'slug.slug as object_slug'])
            ->orderBy('e.end_date')
            ->get();
    }

    public function isEmbargoed(int $objectId): bool
    {
        return $this->getActiveEmbargo($objectId) !== null;
    }

    public function checkAccess(int $objectId, ?int $userId = null, ?string $ipAddress = null): bool
    {
        $embargo = $this->getActiveEmbargo($objectId);
        if (!$embargo) {
            return true;
        }

        $now = date('Y-m-d H:i:s');
        $exceptions = DB::table('embargo_exception')
            ->where('embargo_id', $embargo->id)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->get();

        foreach ($exceptions as $exc) {
            if ($exc->exception_type === 'user' && $userId === $exc->exception_id) {
                return true;
            }
            if ($exc->exception_type === 'ip_range' && $ipAddress) {
                if ($this->isIpInRange($ipAddress, $exc->ip_range_start, $exc->ip_range_end)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isIpInRange(string $ip, string $start, string $end): bool
    {
        $ipLong = ip2long($ip);
        return $ipLong >= ip2long($start) && $ipLong <= ip2long($end);
    }

    public function createEmbargo(int $objectId, array $data, ?int $userId = null): int
    {
        $now = date('Y-m-d H:i:s');
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $status = strtotime($startDate) <= time() ? 'active' : 'pending';

        $embargoId = DB::table('embargo')->insertGetId([
            'object_id' => $objectId,
            'embargo_type' => $data['embargo_type'] ?? 'full',
            'start_date' => $startDate,
            'end_date' => $data['end_date'] ?? null,
            'is_perpetual' => $data['is_perpetual'] ?? false,
            'status' => $status,
            'created_by' => $userId,
            'notify_on_expiry' => $data['notify_on_expiry'] ?? true,
            'notify_days_before' => $data['notify_days_before'] ?? 30,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!empty($data['reason']) || !empty($data['public_message'])) {
            DB::table('embargo_i18n')->insert([
                'embargo_id' => $embargoId,
                'culture' => 'en',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'public_message' => $data['public_message'] ?? null,
            ]);
        }

        return $embargoId;
    }

    public function updateEmbargo(int $embargoId, array $data, ?int $userId = null): bool
    {
        $now = date('Y-m-d H:i:s');
        
        $updateData = [
            'updated_at' => $now,
        ];

        if (isset($data['embargo_type'])) $updateData['embargo_type'] = $data['embargo_type'];
        if (isset($data['start_date'])) $updateData['start_date'] = $data['start_date'];
        if (isset($data['end_date'])) $updateData['end_date'] = $data['end_date'];
        if (isset($data['is_perpetual'])) $updateData['is_perpetual'] = $data['is_perpetual'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['notify_on_expiry'])) $updateData['notify_on_expiry'] = $data['notify_on_expiry'];
        if (isset($data['notify_days_before'])) $updateData['notify_days_before'] = $data['notify_days_before'];

        $updated = DB::table('embargo')
            ->where('id', $embargoId)
            ->update($updateData) > 0;

        // Update i18n
        if (!empty($data['reason']) || !empty($data['public_message']) || !empty($data['notes'])) {
            DB::table('embargo_i18n')
                ->updateOrInsert(
                    ['embargo_id' => $embargoId, 'culture' => 'en'],
                    [
                        'reason' => $data['reason'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'public_message' => $data['public_message'] ?? null,
                    ]
                );
        }

        return $updated;
    }

    public function liftEmbargo(int $embargoId, ?string $reason = null, ?int $userId = null): bool
    {
        return DB::table('embargo')
            ->where('id', $embargoId)
            ->update([
                'status' => 'lifted',
                'lifted_by' => $userId,
                'lifted_at' => date('Y-m-d H:i:s'),
                'lift_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }
}
