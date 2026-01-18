<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use AtomFramework\Contracts\RicSyncContract;
use Illuminate\Database\Capsule\Manager as DB;

class RicSyncService implements RicSyncContract
{
    protected string $fusekiEndpoint;
    protected string $fusekiUsername;
    protected string $fusekiPassword;
    protected string $baseUri = 'https://archives.theahg.co.za/ric/';
    protected array $config = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        // Load from ahg_settings table (AHG Settings UI) - fuseki section
        try {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'fuseki')
                ->get();
            foreach ($rows as $row) {
                $this->config[$row->setting_key] = $row->setting_value;
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }
        $this->fusekiEndpoint = $this->config['fuseki_endpoint'] ?? 'http://localhost:3030/ric';
        $this->fusekiUsername = $this->config['fuseki_username'] ?? 'admin';
        $this->fusekiPassword = $this->config['fuseki_password'] ?? '';
    }

    // =========================================================================
    // DELETION HANDLING
    // =========================================================================

    public function handleDeletion(string $entityType, int $entityId, bool $cascade = true): int
    {
        $ricUri = $this->buildRicUri($entityType, $entityId);
        $triplesRemoved = 0;

        try {
            // Remove triples where entity is SUBJECT
            $triplesRemoved += $this->deleteSubjectTriples($ricUri);

            // If cascade, remove triples where entity is OBJECT
            if ($cascade) {
                $triplesRemoved += $this->deleteObjectTriples($ricUri);
            }

            $this->updateSyncStatus($entityType, $entityId, 'deleted');
            $this->logOperation('delete', $entityType, $entityId, 'success',
                json_encode(['triples_removed' => $triplesRemoved, 'cascade' => $cascade, 'ric_uri' => $ricUri]));

            return $triplesRemoved;
        } catch (\Exception $e) {
            $this->logOperation('delete', $entityType, $entityId, 'failure', $e->getMessage());
            throw $e;
        }
    }

    protected function deleteSubjectTriples(string $uri): int
    {
        $sparql = "DELETE WHERE { <{$uri}> ?p ?o }";
        return $this->executeSparqlUpdate($sparql);
    }

    protected function deleteObjectTriples(string $uri): int
    {
        $sparql = "DELETE WHERE { ?s ?p <{$uri}> }";
        return $this->executeSparqlUpdate($sparql);
    }

    public function handleBatchDeletion(array $entities): array
    {
        $results = ['total' => count($entities), 'success' => 0, 'failed' => 0, 'triples_removed' => 0];
        $uris = [];

        foreach ($entities as $entity) {
            $uris[] = $this->buildRicUri($entity['entity_type'], $entity['entity_id']);
        }

        try {
            $results['triples_removed'] = $this->deleteBatchTriples($uris);
            $results['success'] = count($entities);

            foreach ($entities as $entity) {
                $this->updateSyncStatus($entity['entity_type'], $entity['entity_id'], 'deleted');
            }
        } catch (\Exception $e) {
            $results['failed'] = count($entities);
        }

        return $results;
    }

    protected function deleteBatchTriples(array $uris): int
    {
        if (empty($uris)) return 0;

        $uriList = '<' . implode('>, <', $uris) . '>';
        $count = $this->executeSparqlUpdate("DELETE WHERE { ?s ?p ?o . FILTER(?s IN ({$uriList})) }");
        $count += $this->executeSparqlUpdate("DELETE WHERE { ?s ?p ?o . FILTER(?o IN ({$uriList})) }");

        return $count;
    }

    public function previewDeletion(string $entityType, int $entityId): array
    {
        $ricUri = $this->buildRicUri($entityType, $entityId);
        $preview = ['ric_uri' => $ricUri, 'as_subject' => [], 'as_object' => [], 'total_triples' => 0];

        $results1 = $this->executeSparqlQuery("SELECT ?p ?o WHERE { <{$ricUri}> ?p ?o }");
        foreach ($results1 as $row) {
            $preview['as_subject'][] = ['predicate' => $row['p']['value'] ?? '', 'object' => $row['o']['value'] ?? ''];
        }

        $results2 = $this->executeSparqlQuery("SELECT ?s ?p WHERE { ?s ?p <{$ricUri}> }");
        foreach ($results2 as $row) {
            $preview['as_object'][] = ['subject' => $row['s']['value'] ?? '', 'predicate' => $row['p']['value'] ?? ''];
        }

        $preview['total_triples'] = count($preview['as_subject']) + count($preview['as_object']);
        return $preview;
    }

    // =========================================================================
    // MOVE/HIERARCHY HANDLING
    // =========================================================================

    public function handleMove(string $entityType, int $entityId, ?int $oldParentId, ?int $newParentId): bool
    {
        $ricUri = $this->buildRicUri($entityType, $entityId);

        try {
            if ($oldParentId) {
                $oldParentUri = $this->buildRicUri($entityType, $oldParentId);
                $this->removeParentRelationship($ricUri, $oldParentUri);
            }

            if ($newParentId) {
                $newParentUri = $this->buildRicUri($entityType, $newParentId);
                $this->addParentRelationship($ricUri, $newParentUri);
            }

            $this->updateHierarchyPath($entityType, $entityId, $newParentId);
            $this->updateDescendantHierarchy($entityType, $entityId);

            $this->logOperation('move', $entityType, $entityId, 'success',
                json_encode(['old_parent' => $oldParentId, 'new_parent' => $newParentId]));

            return true;
        } catch (\Exception $e) {
            $this->logOperation('move', $entityType, $entityId, 'failure', $e->getMessage());
            return false;
        }
    }

    protected function removeParentRelationship(string $childUri, string $parentUri): void
    {
        $sparql = "PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
            DELETE WHERE { <{$childUri}> rico:isOrWasIncludedIn <{$parentUri}> . }";
        $this->executeSparqlUpdate($sparql);

        $sparql2 = "PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
            DELETE WHERE { <{$parentUri}> rico:includesOrIncluded <{$childUri}> . }";
        $this->executeSparqlUpdate($sparql2);
    }

    protected function addParentRelationship(string $childUri, string $parentUri): void
    {
        $sparql = "PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
            INSERT DATA {
                <{$childUri}> rico:isOrWasIncludedIn <{$parentUri}> .
                <{$parentUri}> rico:includesOrIncluded <{$childUri}> .
            }";
        $this->executeSparqlUpdate($sparql);
    }

    public function updateHierarchy(string $entityType, int $entityId): int
    {
        $count = 0;
        $hierarchy = $this->getAtomHierarchy($entityType, $entityId);

        foreach ($hierarchy as $i => $id) {
            if ($i < count($hierarchy) - 1) {
                $childUri = $this->buildRicUri($entityType, $hierarchy[$i + 1]);
                $parentUri = $this->buildRicUri($entityType, $id);
                $this->removeParentRelationship($childUri, $parentUri);
                $this->addParentRelationship($childUri, $parentUri);
                $count++;
            }
        }

        return $count;
    }

    protected function updateHierarchyPath(string $entityType, int $entityId, ?int $newParentId): void
    {
        $path = $newParentId ? $this->buildHierarchyPath($entityType, $newParentId) . '/' . $entityId : (string)$entityId;

        DB::table('ric_sync_status')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->update([
                'parent_id' => $newParentId,
                'hierarchy_path' => $path,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    protected function updateDescendantHierarchy(string $entityType, int $entityId): void
    {
        // Get descendants and update their paths
        $descendants = $this->getDescendants($entityType, $entityId);
        foreach ($descendants as $desc) {
            $this->updateHierarchyPath($entityType, $desc['id'], $desc['parent_id']);
        }
    }

    protected function buildHierarchyPath(string $entityType, int $entityId): string
    {
        $path = [];
        $currentId = $entityId;

        while ($currentId) {
            $path[] = $currentId;
            $parent = DB::table('information_object')->where('id', $currentId)->value('parent_id');
            $currentId = $parent ?: null;
        }

        return implode('/', array_reverse($path));
    }

    protected function getDescendants(string $entityType, int $entityId): array
    {
        return DB::table('information_object')
            ->where('parent_id', $entityId)
            ->select('id', 'parent_id')
            ->get()
            ->toArray();
    }

    protected function getAtomHierarchy(string $entityType, int $entityId): array
    {
        $hierarchy = [];
        $currentId = $entityId;

        while ($currentId) {
            $hierarchy[] = $currentId;
            $parent = DB::table('information_object')->where('id', $currentId)->value('parent_id');
            $currentId = $parent ?: null;
        }

        return array_reverse($hierarchy);
    }

    // =========================================================================
    // INTEGRITY CHECKS
    // =========================================================================

    public function findOrphanedTriples(?string $entityType = null): array
    {
        $orphans = [];

        $sparql = "SELECT DISTINCT ?s WHERE {
            ?s ?p ?o .
            FILTER(STRSTARTS(STR(?s), '{$this->baseUri}'))
        }";

        $results = $this->executeSparqlQuery($sparql);

        foreach ($results as $row) {
            $uri = $row['s']['value'] ?? null;
            if (!$uri) continue;

            $parsed = $this->parseRicUri($uri);
            if (!$parsed) continue;

            if ($entityType && $parsed['entity_type'] !== $entityType) continue;

            $exists = $this->atomRecordExists($parsed['entity_type'], $parsed['entity_id']);
            if (!$exists) {
                $orphans[] = [
                    'ric_uri' => $uri,
                    'entity_type' => $parsed['entity_type'],
                    'entity_id' => $parsed['entity_id'],
                ];

                DB::table('ric_orphan_tracking')->updateOrInsert(
                    ['ric_uri' => $uri],
                    [
                        'expected_entity_type' => $parsed['entity_type'],
                        'expected_entity_id' => $parsed['entity_id'],
                        'detected_at' => date('Y-m-d H:i:s'),
                        'detection_method' => 'integrity_check',
                        'status' => 'detected',
                    ]
                );
            }
        }

        return $orphans;
    }

    public function findMissingRecords(?string $entityType = null): array
    {
        $query = DB::table('ric_sync_status')
            ->where('sync_status', 'synced');

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $missing = [];
        $synced = $query->get();

        foreach ($synced as $record) {
            $exists = $this->ricUriExists($record->ric_uri);
            if (!$exists) {
                $missing[] = [
                    'entity_type' => $record->entity_type,
                    'entity_id' => $record->entity_id,
                    'ric_uri' => $record->ric_uri,
                ];
            }
        }

        return $missing;
    }

    public function findInconsistencies(?string $entityType = null): array
    {
        return [];
    }

    public function runIntegrityCheck(): array
    {
        $results = [
            'orphaned_triples' => $this->findOrphanedTriples(),
            'missing_records' => $this->findMissingRecords(),
            'inconsistencies' => $this->findInconsistencies(),
            'checked_at' => date('Y-m-d H:i:s'),
        ];

        $this->logOperation('integrity_check', 'system', null, 'success', json_encode([
            'orphans' => count($results['orphaned_triples']),
            'missing' => count($results['missing_records']),
            'inconsistencies' => count($results['inconsistencies']),
        ]));

        return $results;
    }

    public function cleanupOrphanedTriples(bool $dryRun = false): array
    {
        $stats = ['orphans_found' => 0, 'triples_removed' => 0, 'dry_run' => $dryRun];

        $orphans = $this->findOrphanedTriples();
        $stats['orphans_found'] = count($orphans);

        if (!$dryRun && !empty($orphans)) {
            $uris = array_column($orphans, 'ric_uri');
            $stats['triples_removed'] = $this->deleteBatchTriples($uris);

            foreach ($orphans as $orphan) {
                DB::table('ric_orphan_tracking')
                    ->where('ric_uri', $orphan['ric_uri'])
                    ->update(['status' => 'cleaned', 'resolved_at' => date('Y-m-d H:i:s')]);
            }

            $this->logOperation('cleanup', 'system', null, 'success', json_encode($stats));
        }

        return $stats;
    }

    public function repairInconsistencies(array $inconsistencies): array
    {
        $stats = ['repaired' => 0, 'failed' => 0];
        foreach ($inconsistencies as $issue) {
            try {
                $this->syncRecord($issue['entity_type'], $issue['entity_id'], 'resync');
                $stats['repaired']++;
            } catch (\Exception $e) {
                $stats['failed']++;
            }
        }
        return $stats;
    }

    // =========================================================================
    // SYNC OPERATIONS
    // =========================================================================

    public function syncRecord(string $entityType, int $entityId, string $operation): bool
    {
        return true;
    }

    public function bulkSync(array $records): array
    {
        return [];
    }

    public function fullResync(?string $entityType = null, ?callable $progressCallback = null): array
    {
        return [];
    }

    // =========================================================================
    // AUDIT & LOGGING
    // =========================================================================

    public function logOperation(string $operation, string $entityType, ?int $entityId, string $status, ?string $details = null): void
    {
        DB::table('ric_sync_log')->insert([
            'operation' => $operation,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => $status,
            'details' => $details,
            'triggered_by' => php_sapi_name() === 'cli' ? 'cli' : 'system',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getSyncHistory(string $entityType, int $entityId): array
    {
        return DB::table('ric_sync_log')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function getSyncStats(?string $since = null): array
    {
        $query = DB::table('ric_sync_log');
        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        return [
            'total_operations' => $query->count(),
            'by_operation' => $query->selectRaw('operation, COUNT(*) as count')->groupBy('operation')->pluck('count', 'operation')->toArray(),
            'by_status' => $query->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status')->toArray(),
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    protected function buildRicUri(string $entityType, int $entityId): string
    {
        $typeMap = [
            'informationobject' => 'recordset',
            'actor' => 'agent',
            'repository' => 'corporatebody',
            'function' => 'activity',
            'event' => 'event',
        ];
        $type = $typeMap[$entityType] ?? $entityType;
        return "{$this->baseUri}atom/{$type}/{$entityId}";
    }

    protected function parseRicUri(string $uri): ?array
    {
        if (preg_match('#/atom/(\w+)/(\d+)$#', $uri, $matches)) {
            $typeMap = [
                'recordset' => 'informationobject',
                'agent' => 'actor',
                'corporatebody' => 'repository',
                'activity' => 'function',
                'event' => 'event',
            ];
            return [
                'entity_type' => $typeMap[$matches[1]] ?? $matches[1],
                'entity_id' => (int) $matches[2],
            ];
        }
        return null;
    }

    protected function atomRecordExists(string $entityType, int $entityId): bool
    {
        $tableMap = [
            'informationobject' => 'information_object',
            'actor' => 'actor',
            'repository' => 'repository',
            'function' => 'function_object',
            'event' => 'event',
        ];
        $table = $tableMap[$entityType] ?? $entityType;
        return DB::table($table)->where('id', $entityId)->exists();
    }

    protected function ricUriExists(string $uri): bool
    {
        $sparql = "ASK { <{$uri}> ?p ?o }";
        $result = $this->executeSparqlAsk($sparql);
        return $result;
    }

    protected function updateSyncStatus(string $entityType, int $entityId, string $status): void
    {
        DB::table('ric_sync_status')->updateOrInsert(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            [
                'sync_status' => $status,
                'ric_uri' => $this->buildRicUri($entityType, $entityId),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    protected function executeSparqlQuery(string $sparql): array
    {
        $ch = curl_init($this->fusekiEndpoint . '/query');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $sparql,
            CURLOPT_HTTPHEADER => ['Content-Type: application/sparql-query', 'Accept: application/json'],
            CURLOPT_USERPWD => "{$this->fusekiUsername}:{$this->fusekiPassword}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return $data['results']['bindings'] ?? [];
    }

    protected function executeSparqlUpdate(string $sparql): int
    {
        $ch = curl_init($this->fusekiEndpoint . '/update');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $sparql,
            CURLOPT_HTTPHEADER => ['Content-Type: application/sparql-update'],
            CURLOPT_USERPWD => "{$this->fusekiUsername}:{$this->fusekiPassword}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpCode >= 200 && $httpCode < 300) ? 1 : 0;
    }

    protected function executeSparqlAsk(string $sparql): bool
    {
        $ch = curl_init($this->fusekiEndpoint . '/query');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $sparql,
            CURLOPT_HTTPHEADER => ['Content-Type: application/sparql-query', 'Accept: application/json'],
            CURLOPT_USERPWD => "{$this->fusekiUsername}:{$this->fusekiPassword}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return $data['boolean'] ?? false;
    }
}
