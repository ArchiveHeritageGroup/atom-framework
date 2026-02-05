# Search Configuration Reference

Complete reference for search engine configuration in AtoM AHG Framework.

## Configuration File

Search settings are stored in `plugins/arOpenSearchPlugin/config/search.yml`.

## Full Configuration Example

```yaml
all:
  # ============================================================
  # ENGINE SELECTION
  # ============================================================

  # Search engine to use: opensearch | elasticsearch
  engine: opensearch

  # ============================================================
  # BATCH INDEXING
  # ============================================================

  # Enable batch mode for bulk operations (recommended)
  batch_mode: true

  # Number of documents per batch
  # Higher = faster but more memory
  batch_size: 500

  # ============================================================
  # SERVER CONNECTION
  # ============================================================

  server:
    # Host address
    host: 127.0.0.1

    # Port number
    # Default: 9200 for both OpenSearch and Elasticsearch
    port: 9200

    # Authentication (optional)
    # username: admin
    # password: ${OPENSEARCH_PASSWORD}

    # SSL/TLS settings (optional)
    # ssl:
    #   enabled: true
    #   verify: true
    #   ca_cert: /path/to/ca.pem

    # Request logging (optional, for debugging)
    # log: /var/log/atom/search.log

  # ============================================================
  # INDEX CONFIGURATION
  # ============================================================

  index:
    # Index name prefix
    # Indices will be named: {name}_qubitinformationobject, etc.
    name: atom

    # Index-level settings
    configuration:
      # Number of primary shards
      # More shards = better parallelism, but overhead
      # Recommendation: 1 for <1M docs, 4 for 1-10M, adjust for larger
      number_of_shards: 4

      # Number of replica shards
      # 0 for single-node, 1+ for redundancy
      number_of_replicas: 1

      # Maximum fields per index
      index.mapping.total_fields.limit: 3000

      # Maximum result window (from + size)
      index.max_result_window: 10000

      # --------------------------------------------------------
      # ANALYSIS CONFIGURATION
      # --------------------------------------------------------

      analysis:
        # Analyzers
        analyzer:
          # Default analyzer for text fields
          default:
            tokenizer: standard
            filter: [lowercase, preserved_asciifolding]

          # Autocomplete analyzer
          autocomplete:
            tokenizer: whitespace
            filter: [lowercase, engram, preserved_asciifolding]

          # Language-specific analyzers
          english:
            tokenizer: standard
            filter: [lowercase, english_stop, preserved_asciifolding]

          french:
            tokenizer: standard
            filter: [lowercase, french_stop, preserved_asciifolding, french_elision]

          # ... additional language analyzers

        # Normalizers (for keyword sorting)
        normalizer:
          alphasort:
            type: custom
            filter: [lowercase, preserved_asciifolding]
            char_filter: [punctuation_filter]

        # Token filters
        filter:
          engram:
            type: edgeNGram
            min_gram: 3
            max_gram: 10

          preserved_asciifolding:
            type: asciifolding
            preserve_original: true

          english_stop:
            type: stop
            stopwords: _english_

          french_stop:
            type: stop
            stopwords: _french_

          french_elision:
            type: elision
            articles: [l, m, t, qu, n, s, j, d, c, jusqu, quoiqu, lorsqu, puisqu]

        # Character filters
        char_filter:
          # Strip markdown syntax
          strip_md:
            type: pattern_replace
            pattern: '[\*_#!\[\]\(\)\->`\+\\~:\|\^=]'
            replacement: ' '

          # Remove punctuation
          punctuation_filter:
            type: pattern_replace
            pattern: '["''_\-\?!\.\(\)\[\]#\*`:;]'
            replacement: ''

      # Disable dynamic mapping
      mapper:
        dynamic: false
```

## Configuration Options

### Engine Selection

| Option | Values | Default | Description |
|--------|--------|---------|-------------|
| `engine` | `opensearch`, `elasticsearch` | `opensearch` | Search backend |

### Batch Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `batch_mode` | boolean | `true` | Enable batch indexing |
| `batch_size` | integer | `500` | Documents per batch |

### Server Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `server.host` | string | `127.0.0.1` | Server hostname/IP |
| `server.port` | integer | `9200` | Server port |
| `server.username` | string | - | Auth username |
| `server.password` | string | - | Auth password |
| `server.ssl.enabled` | boolean | `false` | Enable SSL/TLS |
| `server.ssl.verify` | boolean | `true` | Verify SSL certificate |
| `server.ssl.ca_cert` | string | - | Path to CA certificate |
| `server.log` | string | - | Path to request log |

### Index Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `index.name` | string | `atom` | Index prefix |
| `index.configuration.number_of_shards` | integer | `4` | Primary shards |
| `index.configuration.number_of_replicas` | integer | `1` | Replica shards |
| `index.configuration.index.mapping.total_fields.limit` | integer | `3000` | Max fields |
| `index.configuration.index.max_result_window` | integer | `10000` | Max results |

## Environment Variables

Use environment variables for sensitive data:

```yaml
server:
  host: ${SEARCH_HOST:-127.0.0.1}
  port: ${SEARCH_PORT:-9200}
  username: ${OPENSEARCH_USER}
  password: ${OPENSEARCH_PASSWORD}
```

### Setting Environment Variables

```bash
# /etc/environment
SEARCH_HOST=127.0.0.1
SEARCH_PORT=9200
OPENSEARCH_USER=admin
OPENSEARCH_PASSWORD=your-secure-password

# Or in systemd unit file
Environment="OPENSEARCH_PASSWORD=your-secure-password"
```

## Configuration by Environment

### Development

```yaml
all:
  engine: opensearch
  batch_mode: true
  batch_size: 100

  server:
    host: 127.0.0.1
    port: 9200
    # No auth for development
    log: /tmp/search-debug.log

  index:
    name: atom_dev
    configuration:
      number_of_shards: 1
      number_of_replicas: 0
```

### Production

```yaml
all:
  engine: opensearch
  batch_mode: true
  batch_size: 1000

  server:
    host: ${OPENSEARCH_HOST}
    port: 9200
    username: ${OPENSEARCH_USER}
    password: ${OPENSEARCH_PASSWORD}
    ssl:
      enabled: true
      verify: true

  index:
    name: atom_prod
    configuration:
      number_of_shards: 4
      number_of_replicas: 1
```

### Multi-Node Cluster

```yaml
all:
  engine: opensearch

  server:
    # Multiple hosts for cluster
    hosts:
      - node1.example.com:9200
      - node2.example.com:9200
      - node3.example.com:9200
    username: ${OPENSEARCH_USER}
    password: ${OPENSEARCH_PASSWORD}
    ssl:
      enabled: true
      verify: true

  index:
    name: atom
    configuration:
      number_of_shards: 6
      number_of_replicas: 2
```

## Analyzer Configuration

### Adding Custom Analyzers

```yaml
analysis:
  analyzer:
    # Custom analyzer for your language
    afrikaans:
      tokenizer: standard
      filter: [lowercase, afrikaans_stop, preserved_asciifolding]

  filter:
    afrikaans_stop:
      type: stop
      stopwords: [die, en, 'n, van, is, het, te, in, op, vir]
```

### Synonyms

```yaml
analysis:
  filter:
    synonym_filter:
      type: synonym
      synonyms:
        - "photograph,photo,image"
        - "document,record,file"

  analyzer:
    with_synonyms:
      tokenizer: standard
      filter: [lowercase, synonym_filter]
```

## Shard and Replica Guidelines

| Collection Size | Shards | Replicas | Notes |
|-----------------|--------|----------|-------|
| < 100K docs | 1 | 0-1 | Single shard sufficient |
| 100K - 1M docs | 2 | 1 | Minimal sharding |
| 1M - 10M docs | 4 | 1 | Default recommendation |
| 10M - 100M docs | 8-12 | 1-2 | Scale horizontally |
| > 100M docs | 12+ | 2 | Consider time-based indices |

## Validation

### Check Configuration

```bash
php bin/atom search:config --validate
```

### Test Connection

```bash
php bin/atom search:test
```

### View Effective Configuration

```bash
php bin/atom search:config --show
```

## Troubleshooting

### Configuration Not Loading

```bash
# Clear Symfony cache
php symfony cc

# Check for YAML syntax errors
php -r "print_r(yaml_parse_file('plugins/arOpenSearchPlugin/config/search.yml'));"
```

### Environment Variables Not Resolving

```bash
# Verify variable is set
echo $OPENSEARCH_PASSWORD

# Check PHP can access it
php -r "echo getenv('OPENSEARCH_PASSWORD');"
```

### Connection Issues

```yaml
# Enable logging
server:
  log: /var/log/atom/search.log
```

## See Also

- [Architecture](architecture.md)
- [OpenSearch Migration](../OPENSEARCH.md)
- [OpenSearch Configuration Reference](https://opensearch.org/docs/latest/install-and-configure/configuring-opensearch/)
