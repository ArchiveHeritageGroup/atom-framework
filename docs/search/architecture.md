# Search Architecture

This document describes the architecture of the AtoM AHG Framework search abstraction layer.

## Overview

The search abstraction provides a clean separation between application code and search engine implementations. This enables:

- **Engine flexibility**: Switch between OpenSearch, Elasticsearch, or future engines
- **Testability**: Mock search operations in unit tests
- **Maintainability**: Isolate engine-specific code in adapters
- **Migration path**: Run multiple engines in parallel during transitions

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        AtoM Application                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                  │
│  │   Browse    │  │   Search    │  │   Indexing  │                  │
│  │   Actions   │  │   Actions   │  │   Tasks     │                  │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘                  │
│         │                │                │                          │
│         └────────────────┼────────────────┘                          │
│                          │                                           │
│                          ▼                                           │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                 arOpenSearchPlugin                          │  │
│  │                 (AtoM's search integration)                    │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                          │                                           │
│                          ▼                                           │
├─────────────────────────────────────────────────────────────────────┤
│                 ArElasticSearchBridge                                │
│                 (Compatibility layer)                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                 SearchServiceProvider                          │  │
│  │           (Factory / Singleton / Engine Selection)             │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                          │                                           │
│                          ▼                                           │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                 SearchEngineInterface                          │  │
│  │     connect() | index() | search() | delete() | bulk()        │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                          │                                           │
│         ┌────────────────┼────────────────┐                          │
│         │                │                │                          │
│         ▼                ▼                ▼                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                  │
│  │ OpenSearch  │  │Elasticsearch│  │   Future    │                  │
│  │  Adapter    │  │   Adapter   │  │  Adapters   │                  │
│  │  (PRIMARY)  │  │  (Legacy)   │  │             │                  │
│  └──────┬──────┘  └──────┬──────┘  └─────────────┘                  │
│         │                │                                           │
└─────────┼────────────────┼───────────────────────────────────────────┘
          │                │
          ▼                ▼
   ┌─────────────┐  ┌─────────────┐
   │  OpenSearch │  │Elasticsearch│
   │   Server    │  │   Server    │
   │   (3.x)     │  │   (7.x)     │
   └─────────────┘  └─────────────┘
```

## Component Details

### SearchServiceProvider

The central factory that manages search engine instantiation.

```php
class SearchServiceProvider
{
    private static ?SearchEngineInterface $instance = null;

    public static function getInstance(): SearchEngineInterface
    {
        if (self::$instance === null) {
            self::$instance = self::createEngine();
        }
        return self::$instance;
    }

    private static function createEngine(): SearchEngineInterface
    {
        $config = self::loadConfig();

        return match ($config['engine']) {
            'opensearch' => new OpenSearchAdapter($config),
            'elasticsearch' => new ElasticsearchAdapter($config),
            default => throw new InvalidArgumentException("Unknown engine: {$config['engine']}")
        };
    }
}
```

### SearchEngineInterface

The contract that all search adapters must implement.

```php
interface SearchEngineInterface
{
    // Connection Management
    public function connect(array $config): void;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function getVersion(): string;

    // Index Management
    public function createIndex(string $name, array $settings, array $mappings): bool;
    public function deleteIndex(string $name): bool;
    public function indexExists(string $name): bool;
    public function refreshIndex(string $name): void;
    public function getMapping(string $indexName): array;
    public function updateMapping(string $indexName, array $mapping): bool;

    // Document Operations
    public function index(string $indexName, string $id, array $document): bool;
    public function get(string $indexName, string $id): ?array;
    public function delete(string $indexName, string $id): bool;
    public function update(string $indexName, string $id, array $document): bool;

    // Bulk Operations
    public function bulk(array $operations): array;

    // Search
    public function search(string $indexName, SearchQueryInterface $query): SearchResultInterface;

    // Health & Status
    public function health(): array;
    public function stats(string $indexName): array;
}
```

### ArElasticSearchBridge

Provides backward compatibility with AtoM's `arOpenSearchPlugin`.

```php
class ArElasticSearchBridge
{
    private SearchEngineInterface $engine;

    public function __construct()
    {
        $this->engine = SearchServiceProvider::getInstance();
    }

    // Wraps arOpenSearchPlugin methods
    public function addDocument($data, $indexName): void
    {
        $id = $data['id'];
        unset($data['id']);
        $this->engine->index($indexName, $id, $data);
    }

    public function deleteDocument($object): void
    {
        $this->engine->delete(get_class($object), $object->id);
    }

    // ... other bridge methods
}
```

## Data Flow

### Indexing Flow

```
┌──────────┐     ┌──────────────────┐     ┌────────────────┐     ┌────────────┐
│  AtoM    │────▶│arOpenSearch   │────▶│  Bridge /      │────▶│  Search    │
│  Model   │     │  Plugin          │     │  Provider      │     │  Engine    │
│  Save    │     │                  │     │                │     │            │
└──────────┘     └──────────────────┘     └────────────────┘     └────────────┘
     │                    │                       │                     │
     │  Object saved      │  addDocument()        │  index()            │
     │                    │                       │                     │
     └────────────────────┴───────────────────────┴─────────────────────┘
```

### Search Flow

```
┌──────────┐     ┌──────────────────┐     ┌────────────────┐     ┌────────────┐
│  User    │────▶│  Search Action   │────▶│  Query         │────▶│  Search    │
│  Query   │     │  (Browse/Search) │     │  Builder       │     │  Engine    │
└──────────┘     └──────────────────┘     └────────────────┘     └────────────┘
     │                    │                       │                     │
     │  "archival         │  Build ES/OS         │  search()           │
     │   documents"       │  Query DSL           │                     │
     │                    │                       │                     │
     │                    │                       ▼                     │
     │                    │              ┌────────────────┐             │
     │                    │              │  Search        │◀────────────┘
     │                    │◀─────────────│  Result        │
     │                    │              └────────────────┘
     ▼                    ▼
┌──────────────────────────────────────┐
│            Search Results            │
│  (Formatted for display)             │
└──────────────────────────────────────┘
```

## Index Structure

### AtoM Indices

```
atom_qubitinformationobject    ← Archival descriptions
atom_qubitactor                ← Authority records (persons, organizations)
atom_qubitterm                 ← Taxonomy terms (subjects, places)
atom_qubitaccession            ← Accession records
atom_qubitrepository           ← Repository records
atom_qubitfunctionobject       ← Functions (ISDF)
atom_qubitaip                  ← Archival Information Packages
```

### Index Mapping Example

```json
{
  "mappings": {
    "properties": {
      "slug": { "type": "keyword" },
      "identifier": { "type": "keyword" },
      "levelOfDescriptionId": { "type": "integer" },
      "publicationStatusId": { "type": "integer" },
      "i18n": {
        "type": "object",
        "properties": {
          "en": {
            "properties": {
              "title": { "type": "text", "analyzer": "english" },
              "scopeAndContent": { "type": "text" }
            }
          }
        }
      },
      "autocomplete": {
        "type": "text",
        "analyzer": "autocomplete"
      }
    }
  }
}
```

## Configuration Architecture

```yaml
# search.yml structure
all:
  engine: opensearch          # Engine selection

  batch_mode: true            # Batch indexing
  batch_size: 500

  server:                     # Connection settings
    host: 127.0.0.1
    port: 9200
    username: admin           # Optional auth
    password: secret
    ssl:
      enabled: false
      verify: true

  index:                      # Index settings
    name: atom
    configuration:
      number_of_shards: 4
      number_of_replicas: 1
      analysis:               # Analyzers
        analyzer:
          default:
            tokenizer: standard
            filter: [lowercase]
```

## Error Handling

```
┌─────────────────────────────────────────────────────────────────┐
│                      Exception Hierarchy                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  SearchException (base)                                          │
│      │                                                           │
│      ├── ConnectionException                                     │
│      │       └── "Unable to connect to search engine"            │
│      │                                                           │
│      ├── IndexException                                          │
│      │       ├── IndexNotFoundException                          │
│      │       └── MappingException                                │
│      │                                                           │
│      ├── DocumentException                                       │
│      │       └── DocumentNotFoundException                       │
│      │                                                           │
│      └── QueryException                                          │
│              └── "Invalid query syntax"                          │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Performance Considerations

### Batch Operations

For bulk indexing, use batch mode:

```php
// Efficient: batch mode
foreach ($documents as $doc) {
    $search->index($indexName, $doc['id'], $doc);
}
$search->flush();  // Sends batch

// Less efficient: individual requests
foreach ($documents as $doc) {
    $search->indexImmediate($indexName, $doc['id'], $doc);
}
```

### Connection Pooling

The adapter maintains persistent connections:

```php
// Connection reused across requests
$search = SearchServiceProvider::getInstance();
$search->search(...);  // Uses existing connection
$search->search(...);  // Same connection
```

### Index Refresh

Control when changes become searchable:

```php
// Immediate (slower, for real-time needs)
$search->index($name, $id, $doc);
$search->refreshIndex($name);

// Batch (faster, for bulk operations)
$search->index($name, $id, $doc);
// ... more operations
$search->flush();  // Refresh happens automatically
```

## See Also

- [Adapters Documentation](adapters.md)
- [Configuration Reference](configuration.md)
- [OpenSearch Migration](../OPENSEARCH.md)
