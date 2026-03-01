# AtoM Heratio — Queue Engine Feature Overview

**Product:** AtoM Heratio Framework v2.8.2
**Component:** Durable Workflow Engine for Background Jobs
**Author:** The Archive and Heritage Group (Pty) Ltd
**Date:** March 2026

---

## Overview

The Queue Engine provides a durable, reliable background job processing system for AtoM Heratio. It replaces fragile `nohup` process launching with a MySQL-backed queue that survives PHP-FPM restarts, supports automatic retry with exponential backoff, job chaining, batching, rate limiting, and real-time progress tracking.

## Key Features

### Durable Job Processing
- MySQL-backed queue tables with transactional job reservation
- `SELECT ... FOR UPDATE SKIP LOCKED` for safe concurrent worker access
- Jobs persist across PHP-FPM restarts, server reboots, and network interruptions

### Automatic Retry with Backoff
- Configurable maximum attempts per job (default: 3)
- Three backoff strategies: none, linear, exponential
- Failed jobs archived for later inspection and manual retry

### Job Chaining
- Sequential execution of dependent jobs
- Next job in chain automatically activated on predecessor completion
- Chain cancellation propagation on failure

### Batch Processing
- Group related jobs into batches with aggregate progress
- Configurable concurrency limits and rate limiting
- Batch-level callbacks on completion

### Real-Time Progress Tracking
- Per-job progress (current/total/message)
- Per-batch aggregate progress with percentage
- Admin UI with live-updating progress bars

### CLI Management Tools
| Command | Purpose |
|---------|---------|
| `queue:work` | Persistent worker daemon |
| `queue:status` | Per-queue statistics and active workers |
| `queue:retry` | Retry failed jobs (single or all) |
| `queue:failed` | List or flush failed job archive |
| `queue:cleanup` | Purge old completed jobs and logs |

### Admin UI
- Browse all queue jobs with filters (queue, status, job type)
- Job detail view with payload, result data, error traces, and event log timeline
- Batch management with aggregate progress bars
- Retry and cancel actions directly from the UI

### systemd Integration
- Template service unit for automatic worker management
- Per-queue worker instances: `atom-queue-worker@default`, `atom-queue-worker@ai`
- Automatic restart on failure, memory limits, journal logging

### Plugin Adoption
- Existing CLI tasks wrapped via `QueueCliTaskHandler` bridge
- Plugins register handlers in their configuration
- Legacy `nohup` fallback ensures backward compatibility
- Plugins currently integrated: Ingest, Portable Export, AI

## Architecture

```
Queue Engine Architecture

  Plugins (dispatch)          Framework Core           Worker Daemon
  ==================          ==============           =============
  ahgIngestPlugin     -->     QueueService      <--    queue:work
  ahgPortableExport   -->       |                      (systemd)
  ahgAIPlugin         -->       |
                                |
                           ahg_queue_job (MySQL)
                           ahg_queue_batch
                           ahg_queue_failed
                           ahg_queue_log
                           ahg_queue_rate_limit
```

## Technical Requirements

- PHP 8.3+
- MySQL 8.0+
- PCNTL extension (for signal handling in worker)
- systemd (for production worker management)

## Standards Compliance

- OAIS-aligned job lifecycle (pending, running, completed, failed)
- Full audit trail via `ahg_queue_log` table
- Configurable data retention via `queue:cleanup`

---

*AtoM Heratio is developed by The Archive and Heritage Group (Pty) Ltd for GLAM and DAM institutions internationally.*
