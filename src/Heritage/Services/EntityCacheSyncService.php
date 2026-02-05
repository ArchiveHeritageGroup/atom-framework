<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Entity Cache Sync Service.
 *
 * Synchronizes approved NER entities from ahgAIPlugin's ahg_ner_entity table
 * to the heritage_entity_cache table for fast discovery filtering.
 */
class EntityCacheSyncService
{
    /**
     * NER entity type to heritage entity type mapping.
     */
    private const TYPE_MAP = [
        'PERSON' => 'person',
        'PER' => 'person',
        'ORG' => 'organization',
        'GPE' => 'place',
        'LOC' => 'place',
        'DATE' => 'date',
        'TIME' => 'date',
        'EVENT' => 'event',
        'WORK_OF_ART' => 'work',
        'PRODUCT' => 'work',
    ];

    /**
     * Minimum confidence score for sync.
     */
    private float $minConfidence = 0.70;

    /**
     * Set minimum confidence threshold.
     */
    public function setMinConfidence(float $confidence): self
    {
        $this->minConfidence = max(0.0, min(1.0, $confidence));

        return $this;
    }

    /**
     * Get current minimum confidence.
     */
    public function getMinConfidence(): float
    {
        return $this->minConfidence;
    }

    /**
     * Sync approved NER entities to heritage_entity_cache for a specific object.
     *
     * @param int  $objectId The information object ID
     * @param bool $replace  Whether to replace existing NER entries (default true)
     *
     * @return int Number of entities synced
     */
    public function syncFromNer(int $objectId, bool $replace = true): int
    {
        // Get approved/linked NER entities for this object
        $entities = DB::table('ahg_ner_entity')
            ->where('object_id', $objectId)
            ->whereIn('status', ['linked', 'approved'])
            ->where('confidence', '>=', $this->minConfidence)
            ->get();

        if ($entities->isEmpty()) {
            return 0;
        }

        // Remove existing NER entries for this object if replacing
        if ($replace) {
            DB::table('heritage_entity_cache')
                ->where('object_id', $objectId)
                ->where('extraction_method', 'ner')
                ->delete();
        }

        $synced = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($entities as $entity) {
            $heritageType = $this->mapEntityType($entity->entity_type);
            if (!$heritageType) {
                continue;
            }

            $normalizedValue = $this->normalizeValue($entity->entity_value);

            // Check if already exists (for non-replace mode)
            if (!$replace) {
                $exists = DB::table('heritage_entity_cache')
                    ->where('object_id', $objectId)
                    ->where('entity_type', $heritageType)
                    ->where('normalized_value', $normalizedValue)
                    ->exists();

                if ($exists) {
                    continue;
                }
            }

            // Determine source field based on extraction context
            $sourceField = $this->determineSourceField($entity);

            try {
                DB::table('heritage_entity_cache')->insert([
                    'object_id' => $objectId,
                    'entity_type' => $heritageType,
                    'entity_value' => $entity->entity_value,
                    'normalized_value' => $normalizedValue,
                    'confidence_score' => $entity->confidence ?? 1.0,
                    'source_field' => $sourceField,
                    'extraction_method' => 'ner',
                    'created_at' => $now,
                ]);
                $synced++;
            } catch (\Exception $e) {
                // Log but continue - might be duplicate
                error_log("Entity cache sync error for object {$objectId}: " . $e->getMessage());
            }
        }

        return $synced;
    }

    /**
     * Sync all approved NER entities across the system.
     *
     * @param int      $limit    Maximum objects to process
     * @param int|null $sinceId  Only process objects with ID > sinceId
     * @param bool     $dryRun   If true, don't actually sync
     *
     * @return array Summary of sync operation
     */
    public function syncAllApproved(int $limit = 1000, ?int $sinceId = null, bool $dryRun = false): array
    {
        $startTime = microtime(true);

        // Get objects with approved NER entities
        $query = DB::table('ahg_ner_entity')
            ->select('object_id')
            ->whereIn('status', ['linked', 'approved'])
            ->where('confidence', '>=', $this->minConfidence)
            ->groupBy('object_id')
            ->orderBy('object_id')
            ->limit($limit);

        if ($sinceId !== null) {
            $query->where('object_id', '>', $sinceId);
        }

        $objectIds = $query->pluck('object_id')->toArray();

        $results = [
            'objects_processed' => 0,
            'entities_synced' => 0,
            'errors' => [],
            'last_object_id' => null,
            'dry_run' => $dryRun,
            'processing_time_ms' => 0,
        ];

        foreach ($objectIds as $objectId) {
            try {
                if (!$dryRun) {
                    $synced = $this->syncFromNer($objectId);
                    $results['entities_synced'] += $synced;
                } else {
                    // Count what would be synced
                    $count = DB::table('ahg_ner_entity')
                        ->where('object_id', $objectId)
                        ->whereIn('status', ['linked', 'approved'])
                        ->where('confidence', '>=', $this->minConfidence)
                        ->count();
                    $results['entities_synced'] += $count;
                }
                $results['objects_processed']++;
                $results['last_object_id'] = $objectId;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'object_id' => $objectId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $results['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);

        return $results;
    }

    /**
     * Get sync statistics.
     */
    public function getStats(): array
    {
        // Count NER entities by status
        $nerStats = DB::table('ahg_ner_entity')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Count entities in cache by method
        $cacheStats = DB::table('heritage_entity_cache')
            ->select('extraction_method', DB::raw('COUNT(*) as count'))
            ->groupBy('extraction_method')
            ->pluck('count', 'extraction_method')
            ->toArray();

        // Count entities in cache by type
        $cacheTypeStats = DB::table('heritage_entity_cache')
            ->where('extraction_method', 'ner')
            ->select('entity_type', DB::raw('COUNT(*) as count'))
            ->groupBy('entity_type')
            ->pluck('count', 'entity_type')
            ->toArray();

        // Count objects with NER entities
        $objectsWithNer = DB::table('ahg_ner_entity')
            ->whereIn('status', ['linked', 'approved'])
            ->distinct('object_id')
            ->count('object_id');

        // Count objects in cache
        $objectsInCache = DB::table('heritage_entity_cache')
            ->where('extraction_method', 'ner')
            ->distinct('object_id')
            ->count('object_id');

        return [
            'ner_entities_by_status' => $nerStats,
            'cache_entities_by_method' => $cacheStats,
            'cache_ner_entities_by_type' => $cacheTypeStats,
            'objects_with_approved_ner' => $objectsWithNer,
            'objects_in_cache' => $objectsInCache,
            'sync_gap' => max(0, $objectsWithNer - $objectsInCache),
        ];
    }

    /**
     * Remove orphaned cache entries (where source NER entity no longer exists or rejected).
     *
     * @return int Number of entries removed
     */
    public function cleanOrphaned(): int
    {
        // Get object IDs that no longer have approved NER entities
        $orphanedObjects = DB::table('heritage_entity_cache as hec')
            ->leftJoin('ahg_ner_entity as ane', function ($join) {
                $join->on('hec.object_id', '=', 'ane.object_id')
                    ->whereIn('ane.status', ['linked', 'approved']);
            })
            ->where('hec.extraction_method', 'ner')
            ->whereNull('ane.id')
            ->distinct()
            ->pluck('hec.object_id')
            ->toArray();

        if (empty($orphanedObjects)) {
            return 0;
        }

        return DB::table('heritage_entity_cache')
            ->where('extraction_method', 'ner')
            ->whereIn('object_id', $orphanedObjects)
            ->delete();
    }

    /**
     * Map NER entity type to heritage entity type.
     */
    public function mapEntityType(string $nerType): ?string
    {
        $upperType = strtoupper(trim($nerType));

        return self::TYPE_MAP[$upperType] ?? null;
    }

    /**
     * Normalize entity value for matching and deduplication.
     */
    public function normalizeValue(string $value): string
    {
        // Trim whitespace
        $normalized = trim($value);

        // Convert to lowercase
        $normalized = mb_strtolower($normalized, 'UTF-8');

        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Remove common prefixes/suffixes that don't affect identity
        $normalized = preg_replace('/^(the|a|an)\s+/i', '', $normalized);

        // Remove punctuation at the end
        $normalized = rtrim($normalized, '.,;:!?');

        return $normalized;
    }

    /**
     * Determine the source field based on extraction context.
     */
    private function determineSourceField(object $entity): ?string
    {
        // If we have extraction info, try to determine source
        if (!empty($entity->extraction_id)) {
            $extraction = DB::table('ahg_ner_extraction')
                ->where('id', $entity->extraction_id)
                ->first();

            if ($extraction && !empty($extraction->backend_used)) {
                // PDF extraction typically means content field
                if ($extraction->backend_used === 'pdf' || $extraction->backend_used === 'local') {
                    return 'digital_object';
                }
            }
        }

        // Default to scope_and_content as most common source
        return 'scope_and_content';
    }

    /**
     * Get entities that need syncing (approved but not in cache).
     */
    public function getPendingSync(int $limit = 100): array
    {
        // Use COLLATE to handle potential collation mismatches between tables
        return DB::table('ahg_ner_entity as ane')
            ->leftJoin('heritage_entity_cache as hec', function ($join) {
                $join->on('ane.object_id', '=', 'hec.object_id')
                    ->on(DB::raw('LOWER(ane.entity_value) COLLATE utf8mb4_unicode_ci'), '=', 'hec.normalized_value')
                    ->where('hec.extraction_method', '=', 'ner');
            })
            ->whereIn('ane.status', ['linked', 'approved'])
            ->where('ane.confidence', '>=', $this->minConfidence)
            ->whereNull('hec.id')
            ->select(
                'ane.id',
                'ane.object_id',
                'ane.entity_type',
                'ane.entity_value',
                'ane.confidence',
                'ane.status'
            )
            ->orderBy('ane.object_id')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
