<?php
declare(strict_types=1);

namespace AtomFramework\Services;

use AtomFramework\Repositories\SearchHistoryRepository;
use AtomFramework\Repositories\SearchTemplateRepository;
use AtomFramework\Repositories\SavedSearchRepository;
use Illuminate\Database\Capsule\Manager as DB;

class AdvancedSearchService
{
    protected SearchHistoryRepository $historyRepo;
    protected SearchTemplateRepository $templateRepo;
    protected SavedSearchRepository $savedRepo;

    public function __construct()
    {
        $this->historyRepo = new SearchHistoryRepository();
        $this->templateRepo = new SearchTemplateRepository();
        $this->savedRepo = new SavedSearchRepository();
    }

    // =========================================================================
    // HISTORY
    // =========================================================================

    public function recordSearch(array $data): void
    {
        if ($this->getSetting('history_enabled', '1') !== '1') {
            return;
        }
        
        $this->historyRepo->record($data);
    }

    public function getUserHistory(?int $userId, ?string $sessionId, int $limit = 10): array
    {
        return $this->historyRepo->getUserHistory($userId, $sessionId, $limit);
    }

    public function clearHistory(?int $userId, ?string $sessionId): bool
    {
        return $this->historyRepo->clearUserHistory($userId, $sessionId);
    }

    // =========================================================================
    // TEMPLATES
    // =========================================================================

    public function getTemplates(?string $category = null): array
    {
        return $this->templateRepo->getByCategory($category);
    }

    public function getFeaturedTemplates(): array
    {
        return $this->templateRepo->getFeatured();
    }

    public function getTemplate(int $id): ?object
    {
        return $this->templateRepo->findById($id);
    }

    public function getTemplateBySlug(string $slug): ?object
    {
        return $this->templateRepo->findBySlug($slug);
    }

    public function createTemplate(array $data): int
    {
        return $this->templateRepo->create($data);
    }

    public function updateTemplate(int $id, array $data): bool
    {
        return $this->templateRepo->update($id, $data);
    }

    public function deleteTemplate(int $id): bool
    {
        return $this->templateRepo->delete($id);
    }

    // =========================================================================
    // SAVED SEARCHES
    // =========================================================================

    public function getSavedSearches(int $userId): array
    {
        return $this->savedRepo->getUserSearches($userId);
    }

    public function getSavedSearch(int $id): ?object
    {
        return $this->savedRepo->findById($id);
    }

    public function getSavedSearchByToken(string $token): ?object
    {
        return $this->savedRepo->findByToken($token);
    }

    public function saveSearch(array $data): int
    {
        return $this->savedRepo->create($data);
    }

    public function updateSavedSearch(int $id, array $data): bool
    {
        return $this->savedRepo->update($id, $data);
    }

    public function deleteSavedSearch(int $id): bool
    {
        return $this->savedRepo->delete($id);
    }

    public function runSavedSearch(int $id): void
    {
        $this->savedRepo->incrementRunCount($id);
    }

    // =========================================================================
    // POPULAR & SUGGESTIONS
    // =========================================================================

    public function getPopularSearches(int $limit = 10, ?string $entityType = null): array
    {
        return $this->historyRepo->getPopular($limit, $entityType);
    }

    public function getSuggestions(string $term, int $limit = 8): array
    {
        if (strlen($term) < 2) {
            return [];
        }
        
        $suggestions = [];
        
        // From popular searches
        $popular = DB::table('search_popular')
            ->where('search_query', 'LIKE', "%{$term}%")
            ->orderBy('search_count', 'desc')
            ->limit($limit)
            ->get();
        
        foreach ($popular as $p) {
            $suggestions[] = [
                'text' => $p->search_query,
                'type' => 'popular',
                'count' => $p->search_count
            ];
        }
        
        // From templates
        $templates = DB::table('search_template')
            ->where('is_active', 1)
            ->where('name', 'LIKE', "%{$term}%")
            ->limit(3)
            ->get();
        
        foreach ($templates as $t) {
            $suggestions[] = [
                'text' => $t->name,
                'type' => 'template',
                'slug' => $t->slug,
                'icon' => $t->icon
            ];
        }
        
        return array_slice($suggestions, 0, $limit);
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    public function getSetting(string $key, ?string $default = null): ?string
    {
        $setting = DB::table('search_settings')
            ->where('setting_key', $key)
            ->first();
        
        return $setting ? $setting->setting_value : $default;
    }

    public function setSetting(string $key, string $value): void
    {
        DB::table('search_settings')->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
    }
}
