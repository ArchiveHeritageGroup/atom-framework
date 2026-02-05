# Search Adapters

This document explains how search adapters work and how to create custom adapters for new search engines.

## Overview

Adapters implement the `SearchEngineInterface` contract, providing engine-specific implementations for search operations. The framework includes two built-in adapters:

| Adapter | Engine | Status |
|---------|--------|--------|
| `OpenSearchAdapter` | OpenSearch 2.x/3.x | Primary |
| `ElasticsearchAdapter` | Elasticsearch 7.x | Legacy |

## Built-in Adapters

### OpenSearchAdapter

The primary adapter for OpenSearch 2.x and 3.x.

```php
namespace AtomFramework\Search\Adapters;

use OpenSearch\ClientBuilder;
use AtomFramework\Search\Contracts\SearchEngineInterface;

class OpenSearchAdapter implements SearchEngineInterface
{
    protected ?\OpenSearch\Client $client = null;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        if (!empty($config)) {
            $this->connect($config);
        }
    }

    public function connect(array $config): void
    {
        $builder = ClientBuilder::create()
            ->setHosts($config['hosts'] ?? [$config['host'] . ':' . $config['port']]);

        // Authentication
        if (!empty($config['username'])) {
            $builder->setBasicAuthentication(
                $config['username'],
                $config['password'] ?? ''
            );
        }

        // SSL
        if (!empty($config['ssl']['enabled'])) {
            $builder->setSSLVerification($config['ssl']['verify'] ?? true);
        }

        $this->client = $builder->build();
    }

    public function isConnected(): bool
    {
        try {
            $this->client->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ... implement all interface methods
}
```

### ElasticsearchAdapter

Legacy adapter for Elasticsearch 7.x backward compatibility.

```php
namespace AtomFramework\Search\Adapters;

use Elasticsearch\ClientBuilder;
use AtomFramework\Search\Contracts\SearchEngineInterface;

class ElasticsearchAdapter implements SearchEngineInterface
{
    protected ?\Elasticsearch\Client $client = null;

    public function connect(array $config): void
    {
        $this->client = ClientBuilder::create()
            ->setHosts([$config['host'] . ':' . $config['port']])
            ->build();
    }

    // ... implement all interface methods
}
```

## Creating a Custom Adapter

To support a new search engine, implement the `SearchEngineInterface`.

### Step 1: Create the Adapter Class

```php
<?php

namespace AtomFramework\Search\Adapters;

use AtomFramework\Search\Contracts\SearchEngineInterface;
use AtomFramework\Search\Contracts\SearchQueryInterface;
use AtomFramework\Search\Contracts\SearchResultInterface;

class MeilisearchAdapter implements SearchEngineInterface
{
    protected $client;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        if (!empty($config)) {
            $this->connect($config);
        }
    }

    public function connect(array $config): void
    {
        $this->client = new \Meilisearch\Client(
            $config['host'] ?? 'http://localhost:7700',
            $config['api_key'] ?? null
        );
    }

    public function disconnect(): void
    {
        $this->client = null;
    }

    public function isConnected(): bool
    {
        try {
            $this->client->health();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getVersion(): string
    {
        return $this->client->version()['pkgVersion'];
    }

    // Index Management
    public function createIndex(string $name, array $settings, array $mappings): bool
    {
        $this->client->createIndex($name, ['primaryKey' => 'id']);

        // Meilisearch uses different settings approach
        $index = $this->client->index($name);
        $index->updateSettings([
            'searchableAttributes' => array_keys($mappings['properties'] ?? []),
            'filterableAttributes' => $this->extractFilterable($mappings),
        ]);

        return true;
    }

    public function deleteIndex(string $name): bool
    {
        $this->client->deleteIndex($name);
        return true;
    }

    public function indexExists(string $name): bool
    {
        try {
            $this->client->getIndex($name);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function refreshIndex(string $name): void
    {
        // Meilisearch auto-refreshes
    }

    public function getMapping(string $indexName): array
    {
        $settings = $this->client->index($indexName)->getSettings();
        return ['properties' => $settings];
    }

    public function updateMapping(string $indexName, array $mapping): bool
    {
        $index = $this->client->index($indexName);
        $index->updateSettings([
            'searchableAttributes' => array_keys($mapping['properties'] ?? []),
        ]);
        return true;
    }

    // Document Operations
    public function index(string $indexName, string $id, array $document): bool
    {
        $document['id'] = $id;
        $this->client->index($indexName)->addDocuments([$document]);
        return true;
    }

    public function get(string $indexName, string $id): ?array
    {
        try {
            return $this->client->index($indexName)->getDocument($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete(string $indexName, string $id): bool
    {
        $this->client->index($indexName)->deleteDocument($id);
        return true;
    }

    public function update(string $indexName, string $id, array $document): bool
    {
        $document['id'] = $id;
        $this->client->index($indexName)->updateDocuments([$document]);
        return true;
    }

    // Bulk Operations
    public function bulk(array $operations): array
    {
        $results = [];
        $byIndex = [];

        // Group by index
        foreach ($operations as $op) {
            $index = $op['index'] ?? $op['delete'] ?? null;
            if ($index) {
                $byIndex[$index['_index']][] = $op;
            }
        }

        // Process each index
        foreach ($byIndex as $indexName => $ops) {
            $docs = [];
            foreach ($ops as $op) {
                if (isset($op['index'])) {
                    $docs[] = $op['doc'] ?? [];
                }
            }
            if (!empty($docs)) {
                $this->client->index($indexName)->addDocuments($docs);
            }
        }

        return ['errors' => false, 'items' => $results];
    }

    // Search
    public function search(string $indexName, SearchQueryInterface $query): SearchResultInterface
    {
        $params = [
            'q' => $query->getQueryString(),
            'limit' => $query->getSize(),
            'offset' => $query->getFrom(),
        ];

        // Add filters
        if ($filters = $query->getFilters()) {
            $params['filter'] = $this->convertFilters($filters);
        }

        $response = $this->client->index($indexName)->search(
            $params['q'],
            $params
        );

        return new MeilisearchResult($response);
    }

    // Health
    public function health(): array
    {
        $health = $this->client->health();
        return [
            'status' => $health['status'] === 'available' ? 'green' : 'yellow',
            'engine' => 'meilisearch',
            'version' => $this->getVersion(),
        ];
    }

    public function stats(string $indexName): array
    {
        $stats = $this->client->index($indexName)->stats();
        return [
            'docs' => ['count' => $stats['numberOfDocuments']],
            'store' => ['size' => 0],
        ];
    }

    // Helper methods
    protected function extractFilterable(array $mappings): array
    {
        $filterable = [];
        foreach ($mappings['properties'] ?? [] as $field => $config) {
            if (($config['type'] ?? '') === 'keyword') {
                $filterable[] = $field;
            }
        }
        return $filterable;
    }

    protected function convertFilters(array $filters): string
    {
        // Convert ES-style filters to Meilisearch format
        $parts = [];
        foreach ($filters as $filter) {
            if (isset($filter['term'])) {
                foreach ($filter['term'] as $field => $value) {
                    $parts[] = "$field = '$value'";
                }
            }
        }
        return implode(' AND ', $parts);
    }
}
```

### Step 2: Create Result Wrapper

```php
<?php

namespace AtomFramework\Search\Adapters;

use AtomFramework\Search\Contracts\SearchResultInterface;

class MeilisearchResult implements SearchResultInterface
{
    protected array $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function getTotal(): int
    {
        return $this->response['estimatedTotalHits'] ?? count($this->response['hits']);
    }

    public function getHits(): array
    {
        return $this->response['hits'] ?? [];
    }

    public function getAggregations(): array
    {
        return $this->response['facetDistribution'] ?? [];
    }

    public function getScrollId(): ?string
    {
        return null; // Meilisearch uses offset pagination
    }

    public function toArray(): array
    {
        return [
            'total' => $this->getTotal(),
            'hits' => $this->getHits(),
            'aggregations' => $this->getAggregations(),
        ];
    }
}
```

### Step 3: Register the Adapter

Update `SearchServiceProvider` to recognize the new engine:

```php
// In SearchServiceProvider::createEngine()
return match ($config['engine']) {
    'opensearch' => new OpenSearchAdapter($config),
    'elasticsearch' => new ElasticsearchAdapter($config),
    'meilisearch' => new MeilisearchAdapter($config),
    default => throw new InvalidArgumentException("Unknown engine")
};
```

### Step 4: Configuration

Add configuration support in `search.yml`:

```yaml
all:
  engine: meilisearch

  server:
    host: http://localhost:7700
    api_key: your-master-key
```

## Interface Reference

### SearchEngineInterface

| Method | Description |
|--------|-------------|
| `connect(array $config)` | Establish connection to search engine |
| `disconnect()` | Close connection |
| `isConnected()` | Check connection status |
| `getVersion()` | Get engine version |
| `createIndex(string, array, array)` | Create new index with settings/mappings |
| `deleteIndex(string)` | Delete an index |
| `indexExists(string)` | Check if index exists |
| `refreshIndex(string)` | Make recent changes searchable |
| `getMapping(string)` | Get index mapping |
| `updateMapping(string, array)` | Update index mapping |
| `index(string, string, array)` | Index a document |
| `get(string, string)` | Get document by ID |
| `delete(string, string)` | Delete document |
| `update(string, string, array)` | Partial update document |
| `bulk(array)` | Bulk operations |
| `search(string, SearchQueryInterface)` | Execute search |
| `health()` | Get cluster health |
| `stats(string)` | Get index statistics |

### SearchResultInterface

| Method | Description |
|--------|-------------|
| `getTotal()` | Total matching documents |
| `getHits()` | Array of matching documents |
| `getAggregations()` | Facet/aggregation results |
| `getScrollId()` | Scroll ID for pagination |
| `toArray()` | Convert to array |

### SearchQueryInterface

| Method | Description |
|--------|-------------|
| `getQueryString()` | Raw query string |
| `getFrom()` | Offset for pagination |
| `getSize()` | Number of results |
| `getFilters()` | Filter conditions |
| `getSort()` | Sort configuration |
| `getAggregations()` | Aggregation definitions |
| `getSource()` | Fields to return |
| `toArray()` | Convert to engine-specific format |

## Testing Adapters

### Unit Tests

```php
class CustomAdapterTest extends TestCase
{
    public function testConnect()
    {
        $adapter = new CustomAdapter();
        $adapter->connect(['host' => 'localhost', 'port' => 9200]);

        $this->assertTrue($adapter->isConnected());
    }

    public function testIndex()
    {
        $adapter = $this->getConnectedAdapter();

        $result = $adapter->index('test_index', '1', [
            'title' => 'Test Document'
        ]);

        $this->assertTrue($result);

        $doc = $adapter->get('test_index', '1');
        $this->assertEquals('Test Document', $doc['title']);
    }

    public function testSearch()
    {
        $adapter = $this->getConnectedAdapter();
        $query = new SearchQuery(['query_string' => 'test']);

        $results = $adapter->search('test_index', $query);

        $this->assertInstanceOf(SearchResultInterface::class, $results);
        $this->assertGreaterThan(0, $results->getTotal());
    }
}
```

### Integration Tests

```php
class AdapterIntegrationTest extends TestCase
{
    public function testFullWorkflow()
    {
        $adapter = SearchServiceProvider::getInstance();

        // Create index
        $adapter->createIndex('test', [], [
            'properties' => [
                'title' => ['type' => 'text'],
                'status' => ['type' => 'keyword'],
            ]
        ]);

        // Index documents
        $adapter->index('test', '1', ['title' => 'First', 'status' => 'active']);
        $adapter->index('test', '2', ['title' => 'Second', 'status' => 'inactive']);
        $adapter->refreshIndex('test');

        // Search
        $query = new SearchQuery([
            'query_string' => 'First',
            'filters' => [['term' => ['status' => 'active']]]
        ]);
        $results = $adapter->search('test', $query);

        $this->assertEquals(1, $results->getTotal());

        // Cleanup
        $adapter->deleteIndex('test');
    }
}
```

## Best Practices

### Error Handling

```php
public function index(string $indexName, string $id, array $document): bool
{
    try {
        $this->client->index([
            'index' => $indexName,
            'id' => $id,
            'body' => $document,
        ]);
        return true;
    } catch (Missing404Exception $e) {
        throw new IndexNotFoundException("Index not found: $indexName");
    } catch (\Exception $e) {
        throw new DocumentException("Failed to index document: " . $e->getMessage());
    }
}
```

### Connection Management

```php
// Lazy connection
public function ensureConnected(): void
{
    if (!$this->isConnected()) {
        $this->connect($this->config);
    }
}

// Use before operations
public function search(...): SearchResultInterface
{
    $this->ensureConnected();
    // ... perform search
}
```

### Query Translation

When the target engine uses different query syntax:

```php
protected function translateQuery(SearchQueryInterface $query): array
{
    // Convert from ES DSL to target format
    $targetQuery = [];

    if ($must = $query->getMust()) {
        foreach ($must as $clause) {
            $targetQuery['filters'][] = $this->translateClause($clause);
        }
    }

    return $targetQuery;
}
```

## See Also

- [Architecture](architecture.md)
- [Configuration](configuration.md)
- [OpenSearch Adapter Source](../../src/Search/Adapters/OpenSearchAdapter.php)
