<?php
declare(strict_types=1);

namespace AtomFramework\Repositories;

use AtomFramework\Contracts\SavedSearchContract;
use Illuminate\Database\Capsule\Manager as DB;

class SavedSearchRepository implements SavedSearchContract
{
    public function findById(int $id): ?object
    {
        return DB::table('saved_search')->where('id', $id)->first();
    }

    public function findByToken(string $token): ?object
    {
        return DB::table('saved_search')
            ->where('share_token', $token)
            ->where('is_public', 1)
            ->first();
    }

    public function getUserSearches(int $userId, int $limit = 25): array
    {
        return DB::table('saved_search')
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function create(array $data): int
    {
        return DB::table('saved_search')->insertGetId([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'search_params' => is_array($data['search_params']) ? json_encode($data['search_params']) : $data['search_params'],
            'entity_type' => $data['entity_type'] ?? 'informationobject',
            'is_public' => $data['is_public'] ?? 0,
            'share_token' => $data['is_public'] ? bin2hex(random_bytes(16)) : null,
            'notify_new_results' => $data['notify_new_results'] ?? 0,
            'notify_frequency' => $data['notify_frequency'] ?? 'weekly',
            'tags' => $data['tags'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        
        foreach (['name', 'description', 'is_public', 'notify_new_results', 'notify_frequency', 'tags', 'last_result_count', 'last_notified_at', 'last_run_at'] as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }
        
        if (isset($data['search_params'])) {
            $update['search_params'] = is_array($data['search_params']) ? json_encode($data['search_params']) : $data['search_params'];
        }
        
        // Handle share token
        if (isset($data['is_public']) && $data['is_public'] && !isset($update['share_token'])) {
            $existing = DB::table('saved_search')->where('id', $id)->first();
            if (!$existing->share_token) {
                $update['share_token'] = bin2hex(random_bytes(16));
            }
        }
        
        return DB::table('saved_search')->where('id', $id)->update($update) >= 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('saved_search')->where('id', $id)->delete() > 0;
    }

    public function incrementRunCount(int $id): void
    {
        DB::table('saved_search')
            ->where('id', $id)
            ->update([
                'run_count' => DB::raw('run_count + 1'),
                'last_run_at' => date('Y-m-d H:i:s')
            ]);
    }

    public function getSearchesForNotification(string $frequency): array
    {
        return DB::table('saved_search')
            ->where('notify_new_results', 1)
            ->where('notify_frequency', $frequency)
            ->get()
            ->toArray();
    }
}

    public function getGlobal(?string $entityType = null, int $limit = 20): array
    {
        $query = DB::table($this->table)
            ->where('is_global', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->limit($limit);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query->get()->map(fn ($row) => $this->hydrate($row))->toArray();
    }
