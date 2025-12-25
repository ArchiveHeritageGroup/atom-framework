<?php

namespace App\Services\Rights;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        return DB::table('embargo as e')
            ->leftJoin('embargo_i18n as ei', function ($join) {
                $join->on('ei.embargo_id', '=', 'e.id')
                    ->where('ei.culture', '=', 'en');
            })
            ->where('e.object_id', $objectId)
            ->where('e.status', 'active')
            ->where('e.start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('e.end_date')
                    ->orWhere('e.end_date', '>=', now());
            })
            ->select(['e.*', 'ei.reason', 'ei.public_message'])
            ->first();
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

        // Check exceptions
        $exceptions = DB::table('embargo_exception')
            ->where('embargo_id', $embargo->id)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
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
        $embargoId = DB::table('embargo')->insertGetId([
            'object_id' => $objectId,
            'embargo_type' => $data['embargo_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_perpetual' => $data['is_perpetual'] ?? false,
            'status' => Carbon::parse($data['start_date'])->lte(now()) ? 'active' : 'pending',
            'created_by' => $userId,
            'notify_on_expiry' => $data['notify_on_expiry'] ?? true,
            'notify_days_before' => $data['notify_days_before'] ?? 30,
            'created_at' => now(),
            'updated_at' => now(),
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

    public function liftEmbargo(int $embargoId, ?string $reason = null, ?int $userId = null): bool
    {
        return DB::table('embargo')
            ->where('id', $embargoId)
            ->update([
                'status' => 'lifted',
                'lifted_by' => $userId,
                'lifted_at' => now(),
                'lift_reason' => $reason,
                'updated_at' => now(),
            ]) > 0;
    }
}
