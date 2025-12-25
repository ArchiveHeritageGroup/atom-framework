<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\SearchHistoryContract;
use Illuminate\Database\Capsule\Manager as DB;

class SearchHistoryRepository implements SearchHistoryContract
{
    public function record(array $data): int
    {
        // Also update popular searches
        $this->updatePopular($data);
        
        return DB::table('search_history')->insertGetId([
            'user_id' => $data['user_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'search_query' => $data['search_query'] ?? '',
            'search_params' => json_encode($data['search_params'] ?? []),
            'entity_type' => $data['entity_type'] ?? 'informationobject',
            'result_count' => $data['result_count'] ?? 0,
            'execution_time' => $data['execution_time'] ?? 0,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getUserHistory(?int $userId, ?string $sessionId, int $limit = 10): array
    {
        $query = DB::table('search_history');
        
        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return [];
        }
        
        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function clearUserHistory(?int $userId, ?string $sessionId): bool
    {
        $query = DB::table('search_history');
        
        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return false;
        }
        
        return $query->delete() >= 0;
    }

    public function getRecentSearches(int $limit = 10): array
    {
        return DB::table('search_history')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    protected function updatePopular(array $data): void
    {
        $query = $data['search_query'] ?? '';
        if (strlen($query) < 2) return;
        
        $hash = md5(strtolower(trim($query)));
        
        $existing = DB::table('search_popular')
            ->where('search_hash', $hash)
            ->first();
        
        if ($existing) {
            DB::table('search_popular')
                ->where('id', $existing->id)
                ->update([
                    'search_count' => DB::raw('search_count + 1'),
                    'last_searched' => date('Y-m-d H:i:s'),
                    'avg_results' => DB::raw('(avg_results * search_count + ' . (int)($data['result_count'] ?? 0) . ') / (search_count + 1)')
                ]);
        } else {
            DB::table('search_popular')->insert([
                'search_hash' => $hash,
                'search_query' => $query,
                'search_params' => json_encode($data['search_params'] ?? []),
                'entity_type' => $data['entity_type'] ?? 'informationobject',
                'search_count' => 1,
                'avg_results' => $data['result_count'] ?? 0,
                'last_searched' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function getPopular(int $limit = 10, ?string $entityType = null): array
    {
        $query = DB::table('search_popular')
            ->where('search_count', '>=', 3)
            ->orderBy('search_count', 'desc');
        
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        
        return $query->limit($limit)->get()->toArray();
    }

    public function cleanup(int $retentionDays = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        return DB::table('search_history')
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
