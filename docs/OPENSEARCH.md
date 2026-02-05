# OpenSearch Migration Guide

> **Migrating AtoM from Elasticsearch to OpenSearch 3.x**

This document provides a complete guide for migrating AtoM's search backend from Elasticsearch 6.x/7.x to OpenSearch 3.x.

---

## Table of Contents

1. [Overview](#overview)
2. [Why OpenSearch?](#why-opensearch)
3. [Version Information](#version-information)
4. [Architecture](#architecture)
5. [Installation](#installation)
6. [Configuration](#configuration)
7. [Migration Process](#migration-process)
8. [CLI Commands](#cli-commands)
9. [Troubleshooting](#troubleshooting)
10. [Rollback](#rollback)

---

## Overview

The AtoM AHG Framework provides an abstract search interface that supports multiple search backends. The primary supported backend is **OpenSearch 3.x**, with Elasticsearch 7.x available as a legacy fallback.

### Key Benefits

- **Security**: Addresses NESSUS vulnerabilities in ES 6.x/7.x
- **Licensing**: Apache 2.0 (fully open) vs Elasticsearch's SSPL
- **API Compatibility**: OpenSearch is a fork of ES 7.10.2 - queries are compatible
- **Cost**: All features free (no paid tiers)
- **Community**: Active development by AWS and open-source community

---

## Why OpenSearch?

| Concern | Elasticsearch | OpenSearch |
|---------|---------------|------------|
| License | SSPL (restrictive) | Apache 2.0 (open) |
| NESSUS Vulnerabilities | ES 6.x/7.x flagged | 3.x clean |
| API Compatibility | Original | Fork of ES 7.10.2 |
| Community | Elastic-controlled | AWS + community |
| Cost | Paid features | All features free |
| Long-term Support | Uncertain for older versions | Clear maintenance policy |

### NESSUS Security Considerations

Elasticsearch 6.x and 7.x have known vulnerabilities flagged by NESSUS security scans. OpenSearch 3.x addresses these concerns and follows a regular security patch release cycle.

---

## Version Information

### Supported Versions

| Component | Minimum | Recommended | Notes |
|-----------|---------|-------------|-------|
| OpenSearch Server | 2.17.0 | **3.4.0+** | Latest stable recommended |
| opensearch-php | 2.3.0 | **2.5.1+** | Supports OpenSearch 3.x |
| PHP | 8.1 | 8.3 | Match AtoM requirements |

### Compatibility Matrix

| opensearch-php | OpenSearch Server |
|----------------|-------------------|
| 2.5.x | 1.0.0 - 3.x |
| 2.4.x | 1.0.0 - 2.x |
| 2.3.x | 1.0.0 - 2.x |

### Elasticsearch Legacy Support

| elasticsearch-php | Elasticsearch Server |
|-------------------|---------------------|
| 7.x | 7.0 - 7.17 |
| 6.x | 6.0 - 6.8 |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     AtoM Application Layer                       │
├─────────────────────────────────────────────────────────────────┤
│                   SearchServiceProvider                          │
│                (Singleton, Backend Selection)                    │
├─────────────────────────────────────────────────────────────────┤
│                  SearchEngineInterface                           │
│    (Contract: index, search, delete, bulk, aggregate)           │
├──────────────┬──────────────┬───────────────────────────────────┤
│  OpenSearch  │ Elasticsearch│  (Future: Meilisearch, etc.)      │
│  3.x Adapter │  7.x Adapter │                                   │
│  (PRIMARY)   │  (Legacy)    │                                   │
├──────────────┴──────────────┴───────────────────────────────────┤
│               arOpenSearchPlugin (via Bridge)                 │
│               (Backward compatible)                              │
└─────────────────────────────────────────────────────────────────┘
```

### Components

| Component | Location | Purpose |
|-----------|----------|---------|
| SearchEngineInterface | `src/Search/Contracts/` | Abstract contract for search operations |
| OpenSearchAdapter | `src/Search/Adapters/` | OpenSearch 3.x implementation |
| ElasticsearchAdapter | `src/Search/Adapters/` | ES 7.x legacy implementation |
| SearchServiceProvider | `src/Search/` | Factory/singleton for engine selection |
| ArElasticSearchBridge | `src/Search/Bridge/` | Backward compatibility with arOpenSearchPlugin |

### File Structure

```
atom-framework/src/Search/
├── Contracts/
│   ├── SearchEngineInterface.php
│   ├── SearchQueryInterface.php
│   ├── SearchResultInterface.php
│   └── QueryBuilderInterface.php
├── Adapters/
│   ├── OpenSearchAdapter.php
│   ├── ElasticsearchAdapter.php
│   ├── OpenSearchResult.php
│   └── ElasticsearchResult.php
├── Bridge/
│   └── ArElasticSearchBridge.php
├── Migration/
│   ├── IndexMigrator.php
│   ├── DataMigrator.php
│   └── MappingConverter.php
├── SearchServiceProvider.php
└── QueryBuilder.php
```

---

## Installation

### 1. Install OpenSearch Server

#### Ubuntu/Debian

```bash
# Import GPG key
curl -o- https://artifacts.opensearch.org/publickeys/opensearch.pgp | sudo gpg --dearmor --batch --yes -o /usr/share/keyrings/opensearch-keyring

# Add repository
echo "deb [signed-by=/usr/share/keyrings/opensearch-keyring] https://artifacts.opensearch.org/releases/bundle/opensearch/3.x/apt stable main" | sudo tee /etc/apt/sources.list.d/opensearch-3.x.list

# Install
sudo apt update
sudo apt install opensearch

# Start service
sudo systemctl enable opensearch
sudo systemctl start opensearch
```

#### Docker (Development)

```bash
docker run -d \
  --name opensearch \
  -p 9200:9200 \
  -p 9600:9600 \
  -e "discovery.type=single-node" \
  -e "DISABLE_SECURITY_PLUGIN=true" \
  opensearchproject/opensearch:3.4.0
```

### 2. Install PHP Client

```bash
cd /usr/share/nginx/archive/atom-framework
composer require opensearch-project/opensearch-php:^2.5
```

### 3. Verify Installation

```bash
# Check OpenSearch is running
curl -X GET "localhost:9200"

# Expected response:
# {
#   "name" : "node-1",
#   "cluster_name" : "opensearch",
#   "version" : {
#     "number" : "3.4.0",
#     ...
#   }
# }
```

---

## Configuration

### Search Engine Selection

Edit `plugins/arOpenSearchPlugin/config/search.yml`:

```yaml
all:
  # Search engine selection
  engine: opensearch    # opensearch | elasticsearch

  # Batch indexing settings
  batch_mode: true
  batch_size: 500

  server:
    host: 127.0.0.1
    port: 9200

    # OpenSearch authentication (if security plugin enabled)
    # username: admin
    # password: ${OPENSEARCH_PASSWORD}

    # SSL settings (production)
    # ssl:
    #   enabled: true
    #   verify: true

  index:
    name: atom
    configuration:
      number_of_shards: 4
      number_of_replicas: 1
      index.mapping.total_fields.limit: 3000
      index.max_result_window: 10000
```

### Environment Variables

For production, use environment variables for sensitive settings:

```bash
# /etc/environment or .env
OPENSEARCH_HOST=127.0.0.1
OPENSEARCH_PORT=9200
OPENSEARCH_USER=admin
OPENSEARCH_PASSWORD=your-secure-password
OPENSEARCH_SSL_ENABLED=true
```

### OpenSearch Security (Optional)

For production environments with security enabled:

```yaml
server:
  host: ${OPENSEARCH_HOST}
  port: ${OPENSEARCH_PORT}
  username: ${OPENSEARCH_USER}
  password: ${OPENSEARCH_PASSWORD}
  ssl:
    enabled: true
    verify: true
    # ca_cert: /path/to/root-ca.pem
```

---

## Migration Process

### Pre-Migration Checklist

- [ ] Backup existing Elasticsearch indices
- [ ] Document current index mappings
- [ ] Note document counts per index
- [ ] Test OpenSearch installation
- [ ] Plan maintenance window

### Step 1: Parallel Installation

Run OpenSearch alongside Elasticsearch during migration:

```bash
# Elasticsearch on default port 9200
# OpenSearch on alternate port 9201

# Docker example for OpenSearch on 9201
docker run -d \
  --name opensearch-migration \
  -p 9201:9200 \
  -e "discovery.type=single-node" \
  -e "DISABLE_SECURITY_PLUGIN=true" \
  opensearchproject/opensearch:3.4.0
```

### Step 2: Test Connection

```bash
php bin/atom search:test --engine=opensearch --port=9201
```

### Step 3: Migrate Indices

```bash
# Dry run first
php bin/atom search:migrate --from=elasticsearch --to=opensearch --dry-run

# Execute migration
php bin/atom search:migrate --from=elasticsearch --to=opensearch
```

### Step 4: Verify Migration

```bash
# Compare document counts
php bin/atom search:verify

# Run search comparison tests
php bin/atom search:compare-results --query="test search"
```

### Step 5: Switch Configuration

Edit `search.yml`:

```yaml
all:
  engine: opensearch
  server:
    port: 9200  # After moving OpenSearch to main port
```

### Step 6: Decommission Elasticsearch

After successful verification (recommended: 48-hour monitoring period):

```bash
# Stop Elasticsearch
sudo systemctl stop elasticsearch
sudo systemctl disable elasticsearch

# Optional: Remove Elasticsearch
# sudo apt remove elasticsearch
```

---

## CLI Commands

### Connection Testing

```bash
# Test current engine
php bin/atom search:test

# Test specific engine
php bin/atom search:test --engine=opensearch
php bin/atom search:test --engine=elasticsearch
```

### Migration Commands

```bash
# Migrate indices (dry run)
php bin/atom search:migrate --from=elasticsearch --to=opensearch --dry-run

# Migrate indices
php bin/atom search:migrate --from=elasticsearch --to=opensearch

# Migrate specific index
php bin/atom search:migrate --index=QubitInformationObject
```

### Index Management

```bash
# Reindex all data
php bin/atom search:reindex --engine=opensearch

# Reindex specific type
php bin/atom search:reindex --type=informationobject

# Verify index integrity
php bin/atom search:verify
```

### Status Commands

```bash
# Show search engine status
php bin/atom search:status

# Show index statistics
php bin/atom search:stats
```

---

## Troubleshooting

### Common Issues

#### Connection Refused

```
Error: Connection refused [127.0.0.1:9200]
```

**Solution:**
```bash
# Check if OpenSearch is running
sudo systemctl status opensearch

# Check port binding
netstat -tlnp | grep 9200

# Check OpenSearch logs
sudo journalctl -u opensearch -f
```

#### Authentication Failed

```
Error: Authentication failed
```

**Solution:**
- Verify credentials in `search.yml`
- Check OpenSearch security configuration
- For development, disable security plugin:
  ```yaml
  # opensearch.yml
  plugins.security.disabled: true
  ```

#### Mapping Incompatibility

```
Error: mapper_parsing_exception
```

**Solution:**
```bash
# Check mapping differences
php bin/atom search:mapping-diff

# Regenerate mappings
php bin/atom search:migrate --recreate-mappings
```

#### Index Not Found

```
Error: index_not_found_exception
```

**Solution:**
```bash
# List existing indices
curl -X GET "localhost:9200/_cat/indices?v"

# Recreate indices
php symfony search:populate
```

### Performance Issues

#### Slow Indexing

- Increase `batch_size` in configuration
- Ensure adequate heap memory for OpenSearch
- Check disk I/O performance

```yaml
# search.yml
batch_size: 1000  # Increase from default 500
```

#### Slow Searches

- Check number of shards (reduce for small datasets)
- Verify replica count
- Review query complexity

```bash
# Check cluster health
curl -X GET "localhost:9200/_cluster/health?pretty"

# Check shard allocation
curl -X GET "localhost:9200/_cat/shards?v"
```

### Logging

Enable detailed logging for troubleshooting:

```yaml
# search.yml
server:
  log: '/var/log/atom/opensearch.log'
```

---

## Rollback

If issues occur after migration, rollback is straightforward:

### Quick Rollback

1. Change configuration:
   ```yaml
   all:
     engine: elasticsearch
   ```

2. Clear cache:
   ```bash
   php symfony cc
   ```

3. Restart PHP:
   ```bash
   sudo systemctl restart php8.3-fpm
   ```

### Full Rollback

If data migration was performed:

1. Stop OpenSearch
2. Restore Elasticsearch configuration
3. Verify Elasticsearch indices intact
4. Update `search.yml` to use `elasticsearch`
5. Clear cache and restart

### Data Safety

- Original Elasticsearch indices are **not modified** during migration
- Migration creates new indices in OpenSearch
- Both systems can run in parallel during testing

---

## Index Mapping Reference

### AtoM Indices

| Index | OpenSearch Name | Purpose |
|-------|-----------------|---------|
| QubitInformationObject | atom_qubitinformationobject | Archival descriptions |
| QubitActor | atom_qubitactor | Authority records |
| QubitTerm | atom_qubitterm | Taxonomy terms |
| QubitAccession | atom_qubitaccession | Accession records |
| QubitRepository | atom_qubitrepository | Repository records |
| QubitFunctionObject | atom_qubitfunctionobject | Functions |
| QubitAip | atom_qubitaip | AIPs (Archivematica) |

### Mapping Compatibility Notes

OpenSearch 3.x mapping changes from ES 6.x:

| ES 6.x Feature | OpenSearch 3.x |
|----------------|----------------|
| `_all` field | Removed - use `copy_to` |
| Multiple types per index | Single type only |
| `string` type | Use `text` or `keyword` |
| `_doc` type | Optional (can be omitted) |

---

## Security Recommendations

### Production Configuration

```yaml
server:
  ssl:
    enabled: true
    verify: true
  username: ${OPENSEARCH_USER}
  password: ${OPENSEARCH_PASSWORD}
```

### OpenSearch Security Plugin

For full security features:

1. Enable security plugin in `opensearch.yml`
2. Configure TLS certificates
3. Set up internal users
4. Configure role-based access control

### Network Security

- Bind OpenSearch to localhost only (default)
- Use firewall rules if remote access needed
- Consider VPN for remote administration

---

## References

- [OpenSearch Documentation](https://opensearch.org/docs/latest/)
- [OpenSearch PHP Client](https://github.com/opensearch-project/opensearch-php)
- [OpenSearch Release Notes](https://opensearch.org/releases/)
- [Migration from Elasticsearch](https://opensearch.org/docs/latest/upgrade-to/upgrade-to/)
- [AtoM Search Configuration](https://www.accesstomemory.org/docs/latest/)

---

## Changelog

| Date | Version | Changes |
|------|---------|---------|
| 2026-02-01 | 1.0.0 | Initial documentation |

---

**Author:** AtoM AHG Framework Team
**License:** GPL-3.0
