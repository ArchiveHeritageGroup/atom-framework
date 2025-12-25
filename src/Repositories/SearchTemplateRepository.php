<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\SearchTemplateContract;
use Illuminate\Database\Capsule\Manager as DB;

class SearchTemplateRepository implements SearchTemplateContract
{
    public function findById(int $id): ?object
    {
        return DB::table('search_template')->where('id', $id)->first();
    }

    public function findBySlug(string $slug): ?object
    {
        return DB::table('search_template')->where('slug', $slug)->first();
    }

    public function getAll(bool $activeOnly = true): array
    {
        $query = DB::table('search_template')->orderBy('sort_order');
        
        if ($activeOnly) {
            $query->where('is_active', 1);
        }
        
        return $query->get()->toArray();
    }

    public function getFeatured(): array
    {
        return DB::table('search_template')
            ->where('is_active', 1)
            ->where('is_featured', 1)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function getByCategory(?string $category = null): array
    {
        $query = DB::table('search_template')
            ->where('is_active', 1)
            ->orderBy('category')
            ->orderBy('sort_order');
        
        if ($category) {
            $query->where('category', $category);
        }
        
        return $query->get()->toArray();
    }

    public function create(array $data): int
    {
        return DB::table('search_template')->insertGetId([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->generateSlug($data['name']),
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? 'fa-search',
            'color' => $data['color'] ?? 'primary',
            'search_params' => is_array($data['search_params']) ? json_encode($data['search_params']) : $data['search_params'],
            'entity_type' => $data['entity_type'] ?? 'informationobject',
            'category' => $data['category'] ?? null,
            'is_featured' => $data['is_featured'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'show_on_homepage' => $data['show_on_homepage'] ?? 0,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        
        foreach (['name', 'slug', 'description', 'icon', 'color', 'category', 'entity_type', 'is_featured', 'is_active', 'show_on_homepage', 'sort_order'] as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }
        
        if (isset($data['search_params'])) {
            $update['search_params'] = is_array($data['search_params']) ? json_encode($data['search_params']) : $data['search_params'];
        }
        
        return DB::table('search_template')->where('id', $id)->update($update) >= 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('search_template')->where('id', $id)->delete() > 0;
    }

    protected function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $existing = DB::table('search_template')->where('slug', $slug)->count();
        return $existing > 0 ? $slug . '-' . time() : $slug;
    }
}
