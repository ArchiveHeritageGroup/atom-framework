<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Discovery;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Result Fusion Service.
 *
 * Combines, ranks, and filters search results from multiple sources.
 * Applies configurable ranking weights and quality scoring.
 */
class ResultFusionService
{
    private ?object $rankingConfig = null;

    /**
     * Fuse results from multiple search strategies.
     *
     * @param array $resultSets Array of result collections
     * @param array $parsedQuery Parsed query from QueryUnderstandingService
     * @param int|null $institutionId Institution ID for ranking config
     * @return Collection Fused and ranked results
     */
    public function fuse(array $resultSets, array $parsedQuery, ?int $institutionId = null): Collection
    {
        $this->loadRankingConfig($institutionId);

        // Combine all results
        $combined = collect();
        foreach ($resultSets as $source => $results) {
            foreach ($results as $item) {
                $key = $item->id ?? $item['id'] ?? null;
                if ($key && !$combined->has($key)) {
                    $combined[$key] = (object) $item;
                }
            }
        }

        // Calculate scores for each result
        $scored = $combined->map(function ($item) use ($parsedQuery) {
            $item->_relevance_score = $this->calculateRelevanceScore($item, $parsedQuery);
            $item->_quality_score = $this->calculateQualityScore($item);
            $item->_engagement_score = $this->calculateEngagementScore($item);
            $item->_final_score = $this->calculateFinalScore($item);

            return $item;
        });

        // Sort by final score
        $ranked = $scored->sortByDesc('_final_score');

        // Remove scoring metadata from output
        return $ranked->map(function ($item) {
            unset($item->_relevance_score, $item->_quality_score, $item->_engagement_score, $item->_final_score);

            return $item;
        })->values();
    }

    /**
     * Calculate relevance score based on query match.
     */
    public function calculateRelevanceScore(object $item, array $parsedQuery): float
    {
        $score = 0.0;
        $keywords = $parsedQuery['keywords'] ?? [];
        $phrases = $parsedQuery['phrases'] ?? [];

        if (empty($keywords) && empty($phrases)) {
            return 0.5; // Neutral score for browse queries
        }

        $title = strtolower($item->title ?? '');
        $content = strtolower($item->scope_and_content ?? $item->snippet ?? '');
        $identifier = strtolower($item->identifier ?? '');

        // Title match (highest weight)
        foreach ($keywords as $kw) {
            $kw = strtolower($kw);
            if (strpos($title, $kw) !== false) {
                $score += $this->rankingConfig->weight_title_match ?? 1.0;
            }
            if (strpos($content, $kw) !== false) {
                $score += $this->rankingConfig->weight_content_match ?? 0.7;
            }
            if (strpos($identifier, $kw) !== false) {
                $score += $this->rankingConfig->weight_identifier_match ?? 0.9;
            }
        }

        // Phrase match (exact phrase bonus)
        foreach ($phrases as $phrase) {
            $phrase = strtolower($phrase);
            if (strpos($title, $phrase) !== false) {
                $score += 1.5; // Bonus for exact phrase in title
            }
            if (strpos($content, $phrase) !== false) {
                $score += 1.0;
            }
        }

        // Normalize to 0-1 range
        $maxPossible = count($keywords) * 2.6 + count($phrases) * 2.5;

        return $maxPossible > 0 ? min(1.0, $score / $maxPossible) : 0.5;
    }

    /**
     * Calculate quality score based on record completeness.
     */
    public function calculateQualityScore(object $item): float
    {
        $score = 0.0;

        // Has digital object (thumbnail_path indicates this)
        if (!empty($item->thumbnail_path) || !empty($item->thumbnail)) {
            $score += $this->rankingConfig->weight_has_digital_object ?? 0.3;
        }

        // Description length
        $descLength = strlen($item->scope_and_content ?? $item->snippet ?? '');
        if ($descLength > 500) {
            $score += $this->rankingConfig->weight_description_length ?? 0.2;
        } elseif ($descLength > 100) {
            $score += ($this->rankingConfig->weight_description_length ?? 0.2) * 0.5;
        }

        // Has dates
        if (!empty($item->date) || !empty($item->start_date)) {
            $score += $this->rankingConfig->weight_has_dates ?? 0.15;
        }

        // Has subjects (indicated by having subject relations)
        if (!empty($item->subjects) || !empty($item->has_subjects)) {
            $score += $this->rankingConfig->weight_has_subjects ?? 0.15;
        }

        // Penalty for incomplete records
        if (empty($item->title) || strlen($item->title ?? '') < 5) {
            $score *= $this->rankingConfig->penalty_incomplete ?? 0.8;
        }

        return min(1.0, $score);
    }

    /**
     * Calculate engagement score based on user interaction.
     */
    public function calculateEngagementScore(object $item): float
    {
        $score = 0.0;

        // View count (if available)
        $views = $item->view_count ?? $item->hits ?? 0;
        if ($views > 100) {
            $score += $this->rankingConfig->weight_view_count ?? 0.1;
        } elseif ($views > 10) {
            $score += ($this->rankingConfig->weight_view_count ?? 0.1) * 0.5;
        }

        // Download count
        $downloads = $item->download_count ?? 0;
        if ($downloads > 10) {
            $score += $this->rankingConfig->weight_download_count ?? 0.15;
        }

        // Freshness boost (recently updated)
        if (!empty($item->updated_at)) {
            $daysOld = (time() - strtotime($item->updated_at)) / 86400;
            $decayDays = $this->rankingConfig->freshness_decay_days ?? 365;
            if ($daysOld < 30) {
                $score += ($this->rankingConfig->boost_recent ?? 1.1) - 1;
            } elseif ($daysOld < $decayDays) {
                $score += (($this->rankingConfig->boost_recent ?? 1.1) - 1) * (1 - $daysOld / $decayDays);
            }
        }

        return min(1.0, $score);
    }

    /**
     * Calculate final combined score.
     */
    private function calculateFinalScore(object $item): float
    {
        $relevance = $item->_relevance_score ?? 0;
        $quality = $item->_quality_score ?? 0;
        $engagement = $item->_engagement_score ?? 0;

        // Weighted combination (relevance is most important)
        $score = ($relevance * 0.6) + ($quality * 0.25) + ($engagement * 0.15);

        // Apply featured boost
        if (!empty($item->is_featured)) {
            $score *= $this->rankingConfig->boost_featured ?? 1.5;
        }

        return $score;
    }

    /**
     * Deduplicate results (remove near-duplicates).
     */
    public function deduplicate(Collection $results, float $threshold = 0.9): Collection
    {
        $seen = [];
        $unique = collect();

        foreach ($results as $item) {
            $title = strtolower($item->title ?? '');
            $isDuplicate = false;

            foreach ($seen as $seenTitle) {
                if ($this->similarity($title, $seenTitle) > $threshold) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $seen[] = $title;
                $unique->push($item);
            }
        }

        return $unique;
    }

    /**
     * Apply access control filter.
     *
     * @param Collection $results Results to filter
     * @param object|null $user Current user (null for anonymous)
     * @return Collection Filtered results user can access
     */
    public function applyAccessFilter(Collection $results, ?object $user = null): Collection
    {
        // Get user's readable repositories (using existing AclService if available)
        $publishedStatusId = 160; // QubitTerm::PUBLICATION_STATUS_PUBLISHED_ID

        return $results->filter(function ($item) use ($user, $publishedStatusId) {
            // Always allow published items
            if (($item->publication_status_id ?? null) == $publishedStatusId) {
                return true;
            }

            // If user is authenticated and admin, allow all
            if ($user && method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
                return true;
            }

            // Draft items only visible to authenticated users with proper permissions
            // For now, filter out non-published items for anonymous users
            if ($user === null) {
                return false;
            }

            return true; // Authenticated users see their accessible items
        });
    }

    /**
     * Calculate string similarity (0-1).
     */
    private function similarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    /**
     * Load ranking configuration.
     */
    private function loadRankingConfig(?int $institutionId): void
    {
        if ($this->rankingConfig !== null) {
            return;
        }

        $query = DB::table('heritage_ranking_config');

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        $this->rankingConfig = $query->first();

        // Fall back to defaults
        if (!$this->rankingConfig) {
            $this->rankingConfig = (object) [
                'weight_title_match' => 1.0,
                'weight_content_match' => 0.7,
                'weight_identifier_match' => 0.9,
                'weight_subject_match' => 0.8,
                'weight_creator_match' => 0.8,
                'weight_has_digital_object' => 0.3,
                'weight_description_length' => 0.2,
                'weight_has_dates' => 0.15,
                'weight_has_subjects' => 0.15,
                'weight_view_count' => 0.1,
                'weight_download_count' => 0.15,
                'weight_citation_count' => 0.2,
                'boost_featured' => 1.5,
                'boost_recent' => 1.1,
                'penalty_incomplete' => 0.8,
                'freshness_decay_days' => 365,
            ];
        }
    }

    /**
     * Update ranking configuration.
     */
    public function updateRankingConfig(array $data, ?int $institutionId = null): bool
    {
        $existing = DB::table('heritage_ranking_config')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->when(!$institutionId, fn ($q) => $q->whereNull('institution_id'))
            ->first();

        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            return DB::table('heritage_ranking_config')
                ->where('id', $existing->id)
                ->update($data) > 0;
        }

        $data['institution_id'] = $institutionId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_ranking_config')->insert($data);
    }
}
