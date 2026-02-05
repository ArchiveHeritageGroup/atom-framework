# AtoM Search Abstraction Layer

The AtoM AHG Framework provides a search abstraction layer that decouples the application from specific search engine implementations. This allows switching between search backends (OpenSearch, Elasticsearch, etc.) without modifying application code.

## Quick Start

### Using the Search Service

```php
use AtomFramework\Search\SearchServiceProvider;

// Get the search engine instance
$search = SearchServiceProvider::getInstance();

// Index a document
$search->index('QubitInformationObject', '123', [
    'title' => 'My Document',
    'description' => 'Document content...'
]);

// Search
$results = $search->search('QubitInformationObject', $query);

// Delete
$search->delete('QubitInformationObject', '123');
```

### Configuration

The search engine is configured in `plugins/arOpenSearchPlugin/config/search.yml`:

```yaml
all:
  engine: opensearch    # opensearch | elasticsearch
  server:
    host: 127.0.0.1
    port: 9200
```

## Documentation

| Document | Description |
|----------|-------------|
| [Architecture](architecture.md) | System architecture and component diagrams |
| [Adapters](adapters.md) | How to create custom search adapters |
| [Configuration](configuration.md) | Complete configuration reference |
| [../OPENSEARCH.md](../OPENSEARCH.md) | OpenSearch migration guide |

## Supported Engines

| Engine | Adapter | Status |
|--------|---------|--------|
| OpenSearch 3.x | `OpenSearchAdapter` | **Primary** |
| Elasticsearch 7.x | `ElasticsearchAdapter` | Legacy |

## Key Concepts

### SearchEngineInterface

The core contract that all adapters must implement:

```php
interface SearchEngineInterface
{
    // Connection
    public function connect(array $config): void;
    public function isConnected(): bool;

    // Index operations
    public function createIndex(string $name, array $settings, array $mappings): bool;
    public function deleteIndex(string $name): bool;

    // Document operations
    public function index(string $indexName, string $id, array $document): bool;
    public function get(string $indexName, string $id): ?array;
    public function delete(string $indexName, string $id): bool;

    // Search
    public function search(string $indexName, SearchQueryInterface $query): SearchResultInterface;

    // Bulk operations
    public function bulk(array $operations): array;
}
```

### SearchServiceProvider

Factory/singleton that manages engine selection and instantiation:

```php
// The provider reads config and returns appropriate adapter
$engine = SearchServiceProvider::getInstance();

// Engine type is determined by config
// engine: opensearch -> OpenSearchAdapter
// engine: elasticsearch -> ElasticsearchAdapter
```

### Backward Compatibility

The `ArElasticSearchBridge` provides seamless integration with AtoM's existing `arOpenSearchPlugin`, ensuring all existing search functionality continues to work.

## Directory Structure

```
atom-framework/src/Search/
├── Contracts/           # Interfaces
│   ├── SearchEngineInterface.php
│   ├── SearchQueryInterface.php
│   ├── SearchResultInterface.php
│   └── QueryBuilderInterface.php
├── Adapters/            # Engine implementations
│   ├── OpenSearchAdapter.php
│   ├── ElasticsearchAdapter.php
│   └── ...
├── Bridge/              # AtoM integration
│   └── ArElasticSearchBridge.php
├── Migration/           # Migration tools
│   ├── IndexMigrator.php
│   └── DataMigrator.php
└── SearchServiceProvider.php
```

## CLI Commands

```bash
# Test connection
php bin/atom search:test

# Show status
php bin/atom search:status

# Migrate from ES to OpenSearch
php bin/atom search:migrate --from=elasticsearch --to=opensearch

# Reindex
php bin/atom search:reindex
```

## See Also

- [OpenSearch Migration Guide](../OPENSEARCH.md)
- [AtoM Documentation](https://www.accesstomemory.org/docs/)
