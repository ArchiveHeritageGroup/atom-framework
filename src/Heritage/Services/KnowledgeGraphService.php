<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Knowledge Graph Service.
 *
 * Manages the entity relationship graph for heritage discovery.
 * Builds and queries entity co-occurrence networks.
 */
class KnowledgeGraphService
{
    /**
     * Minimum co-occurrence count to create an edge.
     */
    private int $minCoOccurrence = 1;

    /**
     * Minimum confidence for entities.
     */
    private float $minConfidence = 0.70;

    /**
     * Set minimum co-occurrence threshold.
     */
    public function setMinCoOccurrence(int $count): self
    {
        $this->minCoOccurrence = max(1, $count);

        return $this;
    }

    /**
     * Set minimum confidence threshold.
     */
    public function setMinConfidence(float $confidence): self
    {
        $this->minConfidence = max(0.0, min(1.0, $confidence));

        return $this;
    }

    /**
     * Add or update entity node in the graph.
     *
     * @param array $entity Entity data with type, value, actor_id, term_id
     *
     * @return int Node ID
     */
    public function addEntity(array $entity): int
    {
        $type = $entity['entity_type'] ?? $entity['type'] ?? null;
        $value = $entity['entity_value'] ?? $entity['value'] ?? null;
        $confidence = (float) ($entity['confidence'] ?? $entity['confidence_score'] ?? 1.0);

        if (!$type || !$value) {
            throw new \InvalidArgumentException('Entity must have type and value');
        }

        $normalizedValue = $this->normalizeValue($value);
        $now = date('Y-m-d H:i:s');

        // Check if node exists
        $existing = DB::table('heritage_entity_graph_node')
            ->where('entity_type', $type)
            ->where('normalized_value', $normalizedValue)
            ->first();

        if ($existing) {
            // Update occurrence count and confidence average
            $newCount = $existing->occurrence_count + 1;
            $newConfidenceAvg = (($existing->confidence_avg * $existing->occurrence_count) + $confidence) / $newCount;

            DB::table('heritage_entity_graph_node')
                ->where('id', $existing->id)
                ->update([
                    'occurrence_count' => $newCount,
                    'confidence_avg' => $newConfidenceAvg,
                    'last_seen_at' => $now,
                    'actor_id' => $entity['actor_id'] ?? $existing->actor_id,
                    'term_id' => $entity['term_id'] ?? $existing->term_id,
                ]);

            return (int) $existing->id;
        }

        // Create new node
        return (int) DB::table('heritage_entity_graph_node')->insertGetId([
            'entity_type' => $type,
            'canonical_value' => $value,
            'normalized_value' => $normalizedValue,
            'actor_id' => $entity['actor_id'] ?? null,
            'term_id' => $entity['term_id'] ?? null,
            'occurrence_count' => 1,
            'confidence_avg' => $confidence,
            'display_label' => $entity['display_label'] ?? $value,
            'created_at' => $now,
            'updated_at' => $now,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /**
     * Link an object to a node.
     *
     * @param int         $objectId        Information object ID
     * @param int         $nodeId          Graph node ID
     * @param float       $confidence      Confidence score
     * @param string|null $sourceField     Source field name
     * @param string      $extractionMethod Extraction method
     *
     * @return bool Success
     */
    public function linkObjectToNode(
        int $objectId,
        int $nodeId,
        float $confidence = 1.0,
        ?string $sourceField = null,
        string $extractionMethod = 'ner'
    ): bool {
        // Check if link exists
        $existing = DB::table('heritage_entity_graph_object')
            ->where('object_id', $objectId)
            ->where('node_id', $nodeId)
            ->first();

        if ($existing) {
            // Update mention count
            DB::table('heritage_entity_graph_object')
                ->where('id', $existing->id)
                ->update([
                    'mention_count' => $existing->mention_count + 1,
                    'confidence' => max($existing->confidence, $confidence),
                ]);

            return true;
        }

        try {
            DB::table('heritage_entity_graph_object')->insert([
                'object_id' => $objectId,
                'node_id' => $nodeId,
                'confidence' => $confidence,
                'source_field' => $sourceField,
                'extraction_method' => $extractionMethod,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Error linking object to node: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Build co-occurrence edges from entities in an object.
     *
     * @param int $objectId Information object ID
     *
     * @return int Number of edges created/updated
     */
    public function buildCoOccurrenceEdges(int $objectId): int
    {
        // Get all nodes for this object
        $nodes = DB::table('heritage_entity_graph_object')
            ->where('object_id', $objectId)
            ->where('confidence', '>=', $this->minConfidence)
            ->pluck('node_id')
            ->toArray();

        if (count($nodes) < 2) {
            return 0;
        }

        $edgesCreated = 0;
        $now = date('Y-m-d H:i:s');

        // Create edges between all pairs
        for ($i = 0; $i < count($nodes); $i++) {
            for ($j = $i + 1; $j < count($nodes); $j++) {
                $sourceId = min($nodes[$i], $nodes[$j]);
                $targetId = max($nodes[$i], $nodes[$j]);

                $existing = DB::table('heritage_entity_graph_edge')
                    ->where('source_node_id', $sourceId)
                    ->where('target_node_id', $targetId)
                    ->where('relationship_type', 'co_occurrence')
                    ->first();

                if ($existing) {
                    // Update co-occurrence count and add object to source list
                    $sourceObjects = json_decode($existing->source_object_ids ?? '[]', true);
                    if (!in_array($objectId, $sourceObjects)) {
                        $sourceObjects[] = $objectId;
                    }

                    $newWeight = $this->calculateEdgeWeight($existing->co_occurrence_count + 1);

                    DB::table('heritage_entity_graph_edge')
                        ->where('id', $existing->id)
                        ->update([
                            'co_occurrence_count' => $existing->co_occurrence_count + 1,
                            'weight' => $newWeight,
                            'source_object_ids' => json_encode($sourceObjects),
                            'updated_at' => $now,
                        ]);
                } else {
                    try {
                        DB::table('heritage_entity_graph_edge')->insert([
                            'source_node_id' => $sourceId,
                            'target_node_id' => $targetId,
                            'relationship_type' => 'co_occurrence',
                            'weight' => $this->calculateEdgeWeight(1),
                            'co_occurrence_count' => 1,
                            'confidence' => 1.0,
                            'source_object_ids' => json_encode([$objectId]),
                            'is_inferred' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $edgesCreated++;
                    } catch (\Exception $e) {
                        // Might be duplicate, skip
                    }
                }
            }
        }

        return $edgesCreated;
    }

    /**
     * Build graph from entity cache.
     *
     * @param int  $limit   Max objects to process
     * @param bool $rebuild If true, clear existing graph first
     *
     * @return array Build statistics
     */
    public function buildFromCache(int $limit = 5000, bool $rebuild = false): array
    {
        $startTime = microtime(true);

        // Start build log
        $logId = DB::table('heritage_graph_build_log')->insertGetId([
            'build_type' => $rebuild ? 'full' : 'incremental',
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $stats = [
            'nodes_created' => 0,
            'nodes_updated' => 0,
            'edges_created' => 0,
            'objects_processed' => 0,
            'errors' => [],
        ];

        try {
            if ($rebuild) {
                DB::table('heritage_entity_graph_object')->truncate();
                DB::table('heritage_entity_graph_edge')->truncate();
                DB::table('heritage_entity_graph_node')->truncate();
            }

            // Get objects with entities in cache
            $objects = DB::table('heritage_entity_cache')
                ->select('object_id')
                ->where('confidence_score', '>=', $this->minConfidence)
                ->groupBy('object_id')
                ->limit($limit)
                ->pluck('object_id')
                ->toArray();

            foreach ($objects as $objectId) {
                try {
                    // Get entities for this object
                    $entities = DB::table('heritage_entity_cache')
                        ->where('object_id', $objectId)
                        ->where('confidence_score', '>=', $this->minConfidence)
                        ->get();

                    $nodeIds = [];

                    foreach ($entities as $entity) {
                        $existingNode = DB::table('heritage_entity_graph_node')
                            ->where('entity_type', $entity->entity_type)
                            ->where('normalized_value', $entity->normalized_value)
                            ->first();

                        if ($existingNode) {
                            $nodeId = (int) $existingNode->id;
                            $stats['nodes_updated']++;
                        } else {
                            $nodeId = $this->addEntity([
                                'entity_type' => $entity->entity_type,
                                'entity_value' => $entity->entity_value,
                                'confidence' => $entity->confidence_score,
                            ]);
                            $stats['nodes_created']++;
                        }

                        $this->linkObjectToNode(
                            $objectId,
                            $nodeId,
                            (float) $entity->confidence_score,
                            $entity->source_field,
                            $entity->extraction_method
                        );

                        $nodeIds[] = $nodeId;
                    }

                    // Build co-occurrence edges
                    if (count($nodeIds) >= 2) {
                        $stats['edges_created'] += $this->buildCoOccurrenceEdges($objectId);
                    }

                    $stats['objects_processed']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "Object {$objectId}: " . $e->getMessage();
                }
            }

            // Update build log
            DB::table('heritage_graph_build_log')
                ->where('id', $logId)
                ->update([
                    'status' => 'completed',
                    'nodes_created' => $stats['nodes_created'],
                    'nodes_updated' => $stats['nodes_updated'],
                    'edges_created' => $stats['edges_created'],
                    'objects_processed' => $stats['objects_processed'],
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Exception $e) {
            DB::table('heritage_graph_build_log')
                ->where('id', $logId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);

            throw $e;
        }

        $stats['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);

        return $stats;
    }

    /**
     * Get related entities for a node.
     *
     * @param int $nodeId Node ID
     * @param int $depth  Relationship depth (1 = direct, 2 = second degree)
     * @param int $limit  Maximum related entities
     *
     * @return array Related entities
     */
    public function getRelatedEntities(int $nodeId, int $depth = 1, int $limit = 20): array
    {
        $related = [];
        $visited = [$nodeId];

        for ($d = 1; $d <= $depth; $d++) {
            $currentNodes = $d === 1 ? [$nodeId] : array_column($related, 'id');

            $edges = DB::table('heritage_entity_graph_edge as e')
                ->join('heritage_entity_graph_node as n', function ($join) {
                    $join->on('n.id', '=', 'e.target_node_id')
                        ->orOn('n.id', '=', 'e.source_node_id');
                })
                ->where(function ($query) use ($currentNodes) {
                    $query->whereIn('e.source_node_id', $currentNodes)
                        ->orWhereIn('e.target_node_id', $currentNodes);
                })
                ->whereNotIn('n.id', $visited)
                ->where('e.co_occurrence_count', '>=', $this->minCoOccurrence)
                ->orderByDesc('e.weight')
                ->limit($limit)
                ->select(
                    'n.id',
                    'n.entity_type',
                    'n.canonical_value',
                    'n.display_label',
                    'n.occurrence_count',
                    'n.confidence_avg',
                    'n.actor_id',
                    'n.term_id',
                    'e.relationship_type',
                    'e.weight',
                    'e.co_occurrence_count'
                )
                ->get()
                ->toArray();

            foreach ($edges as $edge) {
                if (!in_array($edge->id, $visited)) {
                    $related[] = [
                        'id' => (int) $edge->id,
                        'entity_type' => $edge->entity_type,
                        'value' => $edge->canonical_value,
                        'label' => $edge->display_label ?? $edge->canonical_value,
                        'occurrence_count' => (int) $edge->occurrence_count,
                        'confidence' => (float) $edge->confidence_avg,
                        'actor_id' => $edge->actor_id,
                        'term_id' => $edge->term_id,
                        'relationship' => $edge->relationship_type,
                        'weight' => (float) $edge->weight,
                        'co_occurrences' => (int) $edge->co_occurrence_count,
                        'depth' => $d,
                    ];
                    $visited[] = $edge->id;
                }
            }

            if (count($related) >= $limit) {
                break;
            }
        }

        return array_slice($related, 0, $limit);
    }

    /**
     * Get graph data for visualization (D3.js format).
     *
     * @param array $filters Filter criteria
     * @param int   $limit   Maximum nodes to return
     *
     * @return array Graph data with nodes and links
     */
    public function getGraphData(array $filters = [], int $limit = 100): array
    {
        $nodeQuery = DB::table('heritage_entity_graph_node')
            ->where('occurrence_count', '>', 0)
            ->orderByDesc('occurrence_count')
            ->limit($limit);

        // Apply filters
        if (!empty($filters['entity_type'])) {
            $nodeQuery->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['min_occurrences'])) {
            $nodeQuery->where('occurrence_count', '>=', $filters['min_occurrences']);
        }

        if (!empty($filters['search'])) {
            $nodeQuery->where('canonical_value', 'LIKE', '%' . $filters['search'] . '%');
        }

        $nodes = $nodeQuery->select(
            'id',
            'entity_type',
            'canonical_value',
            'display_label',
            'occurrence_count',
            'confidence_avg',
            'actor_id',
            'term_id'
        )->get();

        if ($nodes->isEmpty()) {
            return ['nodes' => [], 'links' => []];
        }

        $nodeIds = $nodes->pluck('id')->toArray();

        // Get edges between these nodes
        $edges = DB::table('heritage_entity_graph_edge')
            ->whereIn('source_node_id', $nodeIds)
            ->whereIn('target_node_id', $nodeIds)
            ->where('co_occurrence_count', '>=', $this->minCoOccurrence)
            ->orderByDesc('weight')
            ->limit($limit * 3)
            ->select(
                'source_node_id',
                'target_node_id',
                'relationship_type',
                'weight',
                'co_occurrence_count'
            )
            ->get();

        // Format for D3.js
        $d3Nodes = $nodes->map(function ($node) {
            return [
                'id' => (string) $node->id,
                'label' => $node->display_label ?? $node->canonical_value,
                'group' => $this->getGroupForType($node->entity_type),
                'type' => $node->entity_type,
                'size' => min(50, 10 + log1p($node->occurrence_count) * 5),
                'occurrences' => (int) $node->occurrence_count,
                'confidence' => (float) $node->confidence_avg,
                'actor_id' => $node->actor_id,
                'term_id' => $node->term_id,
            ];
        })->toArray();

        $d3Links = $edges->map(function ($edge) {
            return [
                'source' => (string) $edge->source_node_id,
                'target' => (string) $edge->target_node_id,
                'type' => $edge->relationship_type,
                'weight' => (float) $edge->weight,
                'co_occurrences' => (int) $edge->co_occurrence_count,
            ];
        })->toArray();

        return [
            'nodes' => $d3Nodes,
            'links' => $d3Links,
            'stats' => [
                'total_nodes' => count($d3Nodes),
                'total_links' => count($d3Links),
            ],
        ];
    }

    /**
     * Find entity node by value.
     *
     * @param string $entityType Entity type
     * @param string $value      Entity value
     *
     * @return object|null Node object
     */
    public function findNode(string $entityType, string $value): ?object
    {
        $normalized = $this->normalizeValue($value);

        return DB::table('heritage_entity_graph_node')
            ->where('entity_type', $entityType)
            ->where('normalized_value', $normalized)
            ->first();
    }

    /**
     * Get top connected entities.
     *
     * @param string|null $entityType Filter by entity type
     * @param int         $limit      Maximum entities
     *
     * @return array Top entities with connection counts
     */
    public function getTopConnectedEntities(?string $entityType = null, int $limit = 20): array
    {
        $query = DB::table('heritage_entity_graph_node as n')
            ->leftJoin('heritage_entity_graph_edge as e', function ($join) {
                $join->on('n.id', '=', 'e.source_node_id')
                    ->orOn('n.id', '=', 'e.target_node_id');
            })
            ->groupBy(
                'n.id',
                'n.entity_type',
                'n.canonical_value',
                'n.display_label',
                'n.occurrence_count',
                'n.confidence_avg'
            )
            ->orderByRaw('COUNT(e.id) DESC')
            ->limit($limit);

        if ($entityType) {
            $query->where('n.entity_type', $entityType);
        }

        return $query->select(
            'n.id',
            'n.entity_type',
            'n.canonical_value',
            'n.display_label',
            'n.occurrence_count',
            'n.confidence_avg',
            DB::raw('COUNT(e.id) as connection_count'),
            DB::raw('SUM(e.co_occurrence_count) as total_co_occurrences')
        )->get()->toArray();
    }

    /**
     * Get objects containing an entity.
     *
     * @param int $nodeId Node ID
     * @param int $limit  Maximum objects
     *
     * @return array Object IDs with metadata
     */
    public function getObjectsForEntity(int $nodeId, int $limit = 50): array
    {
        return DB::table('heritage_entity_graph_object as go')
            ->join('information_object as io', 'go.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('go.node_id', $nodeId)
            ->orderByDesc('go.confidence')
            ->limit($limit)
            ->select(
                'io.id',
                's.slug',
                'ioi.title',
                'go.confidence',
                'go.mention_count'
            )
            ->get()
            ->toArray();
    }

    /**
     * Get graph statistics.
     */
    public function getStats(): array
    {
        return [
            'total_nodes' => DB::table('heritage_entity_graph_node')->count(),
            'total_edges' => DB::table('heritage_entity_graph_edge')->count(),
            'total_object_links' => DB::table('heritage_entity_graph_object')->count(),
            'nodes_by_type' => DB::table('heritage_entity_graph_node')
                ->select('entity_type', DB::raw('COUNT(*) as count'))
                ->groupBy('entity_type')
                ->pluck('count', 'entity_type')
                ->toArray(),
            'edges_by_type' => DB::table('heritage_entity_graph_edge')
                ->select('relationship_type', DB::raw('COUNT(*) as count'))
                ->groupBy('relationship_type')
                ->pluck('count', 'relationship_type')
                ->toArray(),
            'avg_connections_per_node' => (float) DB::table('heritage_entity_graph_edge')
                ->selectRaw('AVG(cnt) as avg')
                ->fromSub(function ($query) {
                    $query->select(DB::raw('COUNT(*) as cnt'))
                        ->from('heritage_entity_graph_edge')
                        ->groupBy('source_node_id');
                }, 'sub')
                ->value('avg') ?? 0,
        ];
    }

    /**
     * Calculate edge weight based on co-occurrence count.
     */
    private function calculateEdgeWeight(int $coOccurrenceCount): float
    {
        // Logarithmic scaling to prevent very strong edges
        return min(10.0, 1.0 + log1p($coOccurrenceCount));
    }

    /**
     * Normalize value for matching.
     */
    private function normalizeValue(string $value): string
    {
        $normalized = trim($value);
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = preg_replace('/^(the|a|an)\s+/i', '', $normalized);
        $normalized = rtrim($normalized, '.,;:!?');

        return $normalized;
    }

    /**
     * Get group number for entity type (for D3.js coloring).
     */
    private function getGroupForType(string $type): int
    {
        $groups = [
            'person' => 1,
            'organization' => 2,
            'place' => 3,
            'date' => 4,
            'event' => 5,
            'work' => 6,
            'concept' => 7,
        ];

        return $groups[$type] ?? 0;
    }
}
