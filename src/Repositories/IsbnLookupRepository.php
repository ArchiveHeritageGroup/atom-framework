<?php

declare(strict_types=1);

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Repository for ISBN lookup caching and audit operations.
 *
 * Handles caching of ISBN metadata lookups and maintains
 * audit trail of all lookup operations.
 */
class IsbnLookupRepository
{
    /**
     * Cache duration in hours.
     */
    private const CACHE_HOURS = 168; // 7 days

    /**
     * Get cached ISBN metadata.
     */
    public function getCached(string $isbn): ?array
    {
        $normalized = $this->normalizeIsbn($isbn);

        $result = DB::table('atom_isbn_cache')
            ->where(function ($query) use ($normalized) {
                $query->where('isbn', $normalized)
                    ->orWhere('isbn_10', $normalized)
                    ->orWhere('isbn_13', $normalized);
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', DB::raw('NOW()'));
            })
            ->first();

        if (!$result) {
            return null;
        }

        return [
            'id' => $result->id,
            'isbn' => $result->isbn,
            'isbn_10' => $result->isbn_10,
            'isbn_13' => $result->isbn_13,
            'metadata' => json_decode($result->metadata, true),
            'source' => $result->source,
            'oclc_number' => $result->oclc_number,
            'cached_at' => $result->created_at,
        ];
    }

    /**
     * Cache ISBN metadata.
     */
    public function cache(string $isbn, array $metadata, string $source = 'worldcat'): int
    {
        $normalized = $this->normalizeIsbn($isbn);
        $isbn10 = $metadata['isbn_10'] ?? $this->convertToIsbn10($normalized);
        $isbn13 = $metadata['isbn_13'] ?? $this->convertToIsbn13($normalized);

        // Check if exists
        $existing = DB::table('atom_isbn_cache')
            ->where('isbn', $normalized)
            ->first();

        $data = [
            'isbn' => $normalized,
            'isbn_10' => $isbn10,
            'isbn_13' => $isbn13,
            'metadata' => json_encode($metadata),
            'source' => $source,
            'oclc_number' => $metadata['oclc_number'] ?? null,
            'expires_at' => DB::raw('DATE_ADD(NOW(), INTERVAL '.self::CACHE_HOURS.' HOUR)'),
            'updated_at' => DB::raw('NOW()'),
        ];

        if ($existing) {
            DB::table('atom_isbn_cache')
                ->where('id', $existing->id)
                ->update($data);

            return (int) $existing->id;
        }

        $data['created_at'] = DB::raw('NOW()');

        return (int) DB::table('atom_isbn_cache')->insertGetId($data);
    }

    /**
     * Record lookup in audit trail.
     */
    public function audit(array $data): int
    {
        return (int) DB::table('atom_isbn_lookup_audit')->insertGetId([
            'isbn' => $data['isbn'],
            'user_id' => $data['user_id'] ?? null,
            'information_object_id' => $data['information_object_id'] ?? null,
            'source' => $data['source'] ?? 'unknown',
            'success' => $data['success'] ? 1 : 0,
            'fields_populated' => isset($data['fields_populated'])
                ? json_encode($data['fields_populated'])
                : null,
            'error_message' => $data['error_message'] ?? null,
            'lookup_time_ms' => $data['lookup_time_ms'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'created_at' => DB::raw('NOW()'),
        ]);
    }

    /**
     * Get enabled providers ordered by priority.
     */
    public function getProviders(): Collection
    {
        return DB::table('atom_isbn_provider')
            ->where('enabled', 1)
            ->orderBy('priority')
            ->get();
    }

    /**
     * Get provider by slug.
     */
    public function getProvider(string $slug): ?object
    {
        return DB::table('atom_isbn_provider')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Get lookup statistics.
     */
    public function getStatistics(?int $days = 30): array
    {
        $since = DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)");

        $totals = DB::table('atom_isbn_lookup_audit')
            ->select([
                DB::raw('COUNT(*) as total_lookups'),
                DB::raw('SUM(success) as successful'),
                DB::raw('AVG(lookup_time_ms) as avg_time_ms'),
            ])
            ->where('created_at', '>=', $since)
            ->first();

        $bySource = DB::table('atom_isbn_lookup_audit')
            ->select([
                'source',
                DB::raw('COUNT(*) as lookups'),
                DB::raw('SUM(success) as successful'),
            ])
            ->where('created_at', '>=', $since)
            ->groupBy('source')
            ->get();

        $cacheStats = DB::table('atom_isbn_cache')
            ->select([
                DB::raw('COUNT(*) as total_cached'),
                DB::raw('SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as active'),
            ])
            ->first();

        return [
            'period_days' => $days,
            'total_lookups' => (int) $totals->total_lookups,
            'successful_lookups' => (int) $totals->successful,
            'success_rate' => $totals->total_lookups > 0
                ? round(($totals->successful / $totals->total_lookups) * 100, 1)
                : 0,
            'avg_time_ms' => round((float) $totals->avg_time_ms, 0),
            'by_source' => $bySource->toArray(),
            'cache_total' => (int) $cacheStats->total_cached,
            'cache_active' => (int) $cacheStats->active,
        ];
    }

    /**
     * Get recent lookups.
     */
    public function getRecentLookups(int $limit = 20): Collection
    {
        return DB::table('atom_isbn_lookup_audit as a')
            ->leftJoin('user as u', 'a.user_id', '=', 'u.id')
            ->leftJoin('information_object as io', 'a.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->select([
                'a.*',
                'u.username',
                'ioi.title as object_title',
            ])
            ->orderByDesc('a.created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Clear expired cache entries.
     */
    public function clearExpired(): int
    {
        return DB::table('atom_isbn_cache')
            ->where('expires_at', '<', DB::raw('NOW()'))
            ->delete();
    }

    /**
     * Normalize ISBN (remove hyphens and spaces).
     */
    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[\s-]/', '', trim($isbn));
    }

    /**
     * Convert ISBN-13 to ISBN-10.
     */
    private function convertToIsbn10(string $isbn): ?string
    {
        $isbn = $this->normalizeIsbn($isbn);

        if (13 !== strlen($isbn)) {
            return 10 === strlen($isbn) ? $isbn : null;
        }

        // Must start with 978
        if ('978' !== substr($isbn, 0, 3)) {
            return null;
        }

        $isbn9 = substr($isbn, 3, 9);
        $sum = 0;

        for ($i = 0; $i < 9; ++$i) {
            $sum += (int) $isbn9[$i] * (10 - $i);
        }

        $checkDigit = (11 - ($sum % 11)) % 11;
        $checkChar = 10 === $checkDigit ? 'X' : (string) $checkDigit;

        return $isbn9.$checkChar;
    }

    /**
     * Convert ISBN-10 to ISBN-13.
     */
    private function convertToIsbn13(string $isbn): ?string
    {
        $isbn = $this->normalizeIsbn($isbn);

        if (10 !== strlen($isbn)) {
            return 13 === strlen($isbn) ? $isbn : null;
        }

        $isbn12 = '978'.substr($isbn, 0, 9);
        $sum = 0;

        for ($i = 0; $i < 12; ++$i) {
            $sum += (int) $isbn12[$i] * (0 === $i % 2 ? 1 : 3);
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $isbn12.$checkDigit;
    }
}
