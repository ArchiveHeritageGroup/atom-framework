<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Access;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * POPIA Service.
 *
 * Manages POPIA/GDPR privacy compliance flags.
 */
class POPIAService
{
    /**
     * Flag types for personal data.
     */
    public const FLAG_TYPES = [
        'personal_info' => 'Personal Information',
        'sensitive' => 'Sensitive Personal Data',
        'children' => 'Children\'s Data',
        'health' => 'Health Information',
        'biometric' => 'Biometric Data',
        'criminal' => 'Criminal Records',
        'financial' => 'Financial Information',
        'political' => 'Political Opinions',
        'religious' => 'Religious Beliefs',
        'sexual' => 'Sexual Orientation',
    ];

    /**
     * Severity levels.
     */
    public const SEVERITIES = [
        'low' => ['label' => 'Low', 'color' => 'info'],
        'medium' => ['label' => 'Medium', 'color' => 'warning'],
        'high' => ['label' => 'High', 'color' => 'danger'],
        'critical' => ['label' => 'Critical', 'color' => 'dark'],
    ];

    /**
     * Get flags for object.
     */
    public function getObjectFlags(int $objectId): Collection
    {
        return DB::table('heritage_popia_flag')
            ->where('object_id', $objectId)
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unresolved flags for object.
     */
    public function getUnresolvedFlags(int $objectId): Collection
    {
        return DB::table('heritage_popia_flag')
            ->where('object_id', $objectId)
            ->where('is_resolved', 0)
            ->orderBy('severity', 'desc')
            ->get();
    }

    /**
     * Get all unresolved flags (admin dashboard).
     */
    public function getAllUnresolvedFlags(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;
        $severity = $params['severity'] ?? null;
        $flagType = $params['flag_type'] ?? null;

        $query = DB::table('heritage_popia_flag')
            ->leftJoin('information_object', 'heritage_popia_flag.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->select([
                'heritage_popia_flag.*',
                'information_object.slug',
                'information_object_i18n.title as object_title',
            ])
            ->where('heritage_popia_flag.is_resolved', 0);

        if ($severity) {
            $query->where('heritage_popia_flag.severity', $severity);
        }

        if ($flagType) {
            $query->where('heritage_popia_flag.flag_type', $flagType);
        }

        $total = $query->count();

        $flags = $query->orderByRaw("FIELD(heritage_popia_flag.severity, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('heritage_popia_flag.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'flags' => $flags,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Create POPIA flag.
     */
    public function createFlag(array $data): int
    {
        return (int) DB::table('heritage_popia_flag')->insertGetId([
            'object_id' => $data['object_id'],
            'flag_type' => $data['flag_type'],
            'severity' => $data['severity'] ?? 'medium',
            'description' => $data['description'] ?? null,
            'affected_fields' => isset($data['affected_fields']) ? json_encode($data['affected_fields']) : null,
            'detected_by' => $data['detected_by'] ?? 'manual',
            'is_resolved' => 0,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Resolve POPIA flag.
     */
    public function resolveFlag(int $id, int $resolvedBy, ?string $notes = null): bool
    {
        return DB::table('heritage_popia_flag')
            ->where('id', $id)
            ->update([
                'is_resolved' => 1,
                'resolution_notes' => $notes,
                'resolved_by' => $resolvedBy,
                'resolved_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Update flag.
     */
    public function updateFlag(int $id, array $data): bool
    {
        $allowedFields = ['flag_type', 'severity', 'description', 'affected_fields'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (isset($updateData['affected_fields']) && is_array($updateData['affected_fields'])) {
            $updateData['affected_fields'] = json_encode($updateData['affected_fields']);
        }

        return DB::table('heritage_popia_flag')
            ->where('id', $id)
            ->update($updateData) >= 0;
    }

    /**
     * Delete flag.
     */
    public function deleteFlag(int $id): bool
    {
        return DB::table('heritage_popia_flag')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Check if object has any POPIA flags.
     */
    public function hasFlags(int $objectId): bool
    {
        return DB::table('heritage_popia_flag')
            ->where('object_id', $objectId)
            ->where('is_resolved', 0)
            ->exists();
    }

    /**
     * Get highest severity for object.
     */
    public function getHighestSeverity(int $objectId): ?string
    {
        $flag = DB::table('heritage_popia_flag')
            ->where('object_id', $objectId)
            ->where('is_resolved', 0)
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->first();

        return $flag ? $flag->severity : null;
    }

    /**
     * Get POPIA statistics.
     */
    public function getStats(): array
    {
        $unresolved = DB::table('heritage_popia_flag')
            ->where('is_resolved', 0)
            ->count();

        $critical = DB::table('heritage_popia_flag')
            ->where('is_resolved', 0)
            ->where('severity', 'critical')
            ->count();

        $high = DB::table('heritage_popia_flag')
            ->where('is_resolved', 0)
            ->where('severity', 'high')
            ->count();

        $byType = DB::table('heritage_popia_flag')
            ->where('is_resolved', 0)
            ->select('flag_type', DB::raw('COUNT(*) as count'))
            ->groupBy('flag_type')
            ->pluck('count', 'flag_type')
            ->toArray();

        $resolvedThisMonth = DB::table('heritage_popia_flag')
            ->where('is_resolved', 1)
            ->where('resolved_at', '>=', date('Y-m-01'))
            ->count();

        return [
            'unresolved' => $unresolved,
            'critical' => $critical,
            'high' => $high,
            'by_type' => $byType,
            'resolved_this_month' => $resolvedThisMonth,
        ];
    }
}
