# AtoM Heratio — Queue Engine Technical Manual

**Product:** AtoM Heratio Framework v2.8.2
**Component:** Durable Workflow Engine for Background Jobs
**Author:** The Archive and Heritage Group (Pty) Ltd
**Date:** March 2026

---

## Table of Contents

1. [Architecture](#1-architecture)
2. [Database Schema](#2-database-schema)
3. [QueueService API](#3-queueservice-api)
4. [Plugin Migration Guide](#4-plugin-migration-guide)
5. [Job Lifecycle](#5-job-lifecycle)
6. [File Reference](#6-file-reference)

---

## 1. Architecture

### Design Rationale

The queue engine uses MySQL-backed queues rather than `illuminate/queue` because the full Laravel Application container (`Container::make()` resolution) conflicts with the Symfony 1.4 runtime. The custom approach is proven (ahgAIPlugin's `JobQueueService`, 1105 lines) and avoids 6+ new dependencies.

### Component Diagram

```
atom-framework (new files)
  src/Contracts/QueueJobInterface.php     — Handler interface
  src/Services/QueueJobContext.php        — Context for progress/logging
  src/Services/QueueService.php           — Core service (~700 lines)
  src/Services/QueueJobRegistry.php       — Static handler registry
  src/Services/QueueCliTaskHandler.php    — CLI task bridge
  src/Console/Commands/Jobs/Queue*.php    — 5 CLI commands

ahgJobsManagePlugin (extended)
  lib/Services/QueueJobsService.php       — Admin query service
  modules/jobsManage/actions/actions.class.php  — Queue actions
  modules/jobsManage/templates/queue*.php       — Queue templates

Plugins (adopt incrementally)
  Plugin config → QueueJobRegistry::register()
  Action code → QueueService::dispatch() with nohup fallback
```

### Worker Reservation Pattern

Uses `SELECT ... FOR UPDATE SKIP LOCKED` for safe concurrent reservation:

```php
DB::connection()->transaction(function () {
    $job = DB::table('ahg_queue_job')
        ->whereIn('queue', $queues)
        ->where('status', 'pending')
        ->where('available_at', '<=', $now)
        ->orderBy('priority')
        ->orderBy('available_at')
        ->orderBy('id')
        ->lockForUpdate()  // FOR UPDATE SKIP LOCKED
        ->first();

    if ($job) {
        DB::table('ahg_queue_job')
            ->where('id', $job->id)
            ->update(['status' => 'reserved', ...]);
    }
});
```

---

## 2. Database Schema

### ahg_queue_job

| Column | Type | Purpose |
|--------|------|---------|
| id | BIGINT PK | Auto-increment ID |
| queue | VARCHAR(100) | Queue name: default, ai, ingest, export, sync |
| job_type | VARCHAR(255) | Handler identifier (e.g. `ingest:commit`) |
| payload | JSON | Serialized arguments |
| status | VARCHAR(20) | pending, reserved, running, completed, failed, cancelled |
| priority | TINYINT | 1=highest, 9=lowest |
| batch_id | BIGINT NULL | FK to ahg_queue_batch |
| chain_id | BIGINT NULL | Links sequential chain jobs |
| chain_order | INT NULL | Order within chain (0-based) |
| attempt_count | INT | Current attempt number |
| max_attempts | INT | Default 3 |
| delay_seconds | INT | Delay before first attempt |
| backoff_strategy | VARCHAR(20) | none, linear, exponential |
| available_at | DATETIME | When job becomes available |
| reserved_at | DATETIME | When worker claimed job |
| started_at | DATETIME | When processing began |
| completed_at | DATETIME | When processing ended |
| processing_time_ms | INT | Duration in milliseconds |
| result_data | JSON | Return data from handler |
| error_message | TEXT | Last error message |
| error_code | VARCHAR(100) | Error code |
| error_trace | TEXT | Stack trace |
| worker_id | VARCHAR(100) | Worker process ID (hostname:pid) |
| user_id | INT | Dispatching user |
| progress_current | INT | Progress numerator |
| progress_total | INT | Progress denominator |
| progress_message | VARCHAR(500) | Status text |
| rate_limit_group | VARCHAR(100) | Rate limiter group |

Key indexes: `(queue, status, priority, available_at)` for dispatch, `(batch_id)`, `(chain_id, chain_order)`.

### ahg_queue_batch

Batch groups with progress tracking, concurrency limits, and completion callbacks.

### ahg_queue_failed

Archived jobs after max retries. Preserves queue, job_type, payload, error info, and original_job_id.

### ahg_queue_log

Audit trail with event_type: dispatched, reserved, started, completed, failed, retried, cancelled.

### ahg_queue_rate_limit

Per-group sliding window rate limiter (max_per_minute with 1-minute windows).

---

## 3. QueueService API

### Dispatch

```php
use AtomFramework\Services\QueueService;

$qs = new QueueService();

// Simple dispatch
$jobId = $qs->dispatch('ingest:commit', ['task' => 'ingest:commit', 'args' => '--job-id=123'], 'ingest');

// Full options
$jobId = $qs->dispatch(
    jobType: 'ingest:commit',
    payload: ['task' => 'ingest:commit', 'args' => '--job-id=123'],
    queue: 'ingest',
    priority: 3,         // 1=highest, 9=lowest
    delaySeconds: 60,    // Delay before first attempt
    maxAttempts: 1,      // No retry
    userId: $userId,
    rateLimitGroup: 'ingest'
);

// Synchronous (blocks until complete)
$result = $qs->dispatchSync('ingest:commit', ['task' => '...']);
```

### Chain

```php
$chainId = $qs->dispatchChain([
    ['job_type' => 'export:prepare', 'payload' => [...]],
    ['job_type' => 'export:generate', 'payload' => [...]],
    ['job_type' => 'export:notify', 'payload' => [...]],
], 'export', $userId);
```

### Batch

```php
$batchId = $qs->createBatch([
    'name' => 'NER extraction batch',
    'queue' => 'ai',
    'max_concurrent' => 3,
    'user_id' => $userId,
]);

$qs->addToBatch($batchId, [
    ['job_type' => 'ai:ner-extract', 'payload' => ['object_id' => 100]],
    ['job_type' => 'ai:ner-extract', 'payload' => ['object_id' => 101]],
]);

$qs->startBatch($batchId);
```

### Progress

```php
$progress = $qs->getProgress($jobId);
// ['found' => true, 'status' => 'running', 'current' => 50, 'total' => 100, 'percent' => 50.0, ...]

$batchProgress = $qs->getBatchProgress($batchId);
// ['found' => true, 'status' => 'running', 'total_jobs' => 10, 'completed_jobs' => 7, ...]
```

### Management

```php
$qs->cancelJob($jobId);
$qs->cancelBatch($batchId);
$qs->retryFailed($failedId);
$qs->retryAllFailed();
$qs->flushFailed();
$qs->cleanup(30);  // Purge >30 days
$qs->recoverStale(10);  // Recover jobs stale >10 min
```

---

## 4. Plugin Migration Guide

### Step 1: Register Handler

In your plugin's `PluginConfiguration.class.php`:

```php
public function initialize()
{
    // ... existing code ...

    if (class_exists('\AtomFramework\Services\QueueJobRegistry')) {
        \AtomFramework\Services\QueueJobRegistry::register(
            'your:task',
            \AtomFramework\Services\QueueCliTaskHandler::class
        );
    }
}
```

### Step 2: Replace nohup with dispatch

In your action method, replace:

```php
// OLD
$cmd = sprintf('nohup php %s/symfony your:task --id=%d > /dev/null 2>&1 &', $root, $id);
exec($cmd);
```

With:

```php
// NEW (with fallback)
$dispatched = false;
try {
    if (class_exists('\AtomFramework\Services\QueueService')) {
        $qs = new \AtomFramework\Services\QueueService();
        $qs->dispatch('your:task', [
            'task' => 'your:task',
            'args' => '--id=' . $id,
        ], 'default', 5, 0, 1, $userId);
        $dispatched = true;
    }
} catch (\Throwable $e) {
    // Fall through to nohup
}

if (!$dispatched) {
    // Legacy nohup fallback
    $cmd = sprintf('nohup php %s/symfony your:task --id=%d > /dev/null 2>&1 &', $root, $id);
    exec($cmd);
}
```

### Step 3: Custom Handler (Optional)

For more control, implement `QueueJobInterface`:

```php
namespace YourPlugin\Services;

use AtomFramework\Contracts\QueueJobInterface;
use AtomFramework\Services\QueueJobContext;

class YourJobHandler implements QueueJobInterface
{
    public function handle(array $payload, QueueJobContext $context): array
    {
        $context->progress(0, 100, 'Starting...');

        // Your processing logic
        for ($i = 0; $i < 100; $i++) {
            if ($context->isCancelled()) {
                return ['cancelled' => true];
            }
            // ... process ...
            $context->progress($i + 1, 100, "Processing item {$i}");
        }

        return ['success' => true, 'processed' => 100];
    }

    public function maxAttempts(): int { return 3; }
    public function timeout(): int { return 600; }
}
```

Register in plugin config:

```php
QueueJobRegistry::register('your:task', \YourPlugin\Services\YourJobHandler::class);
```

---

## 5. Job Lifecycle

```
dispatch()
  |
  v
PENDING (available_at in future if delayed)
  |
  v (worker polls)
RESERVED (locked by worker via FOR UPDATE SKIP LOCKED)
  |
  v (worker calls handler)
RUNNING (started_at set, progress updates)
  |
  +---> COMPLETED (result_data saved, chain advanced)
  |
  +---> FAILED
          |
          +---> if attempts < max_attempts:
          |       PENDING (backoff delay applied, retry)
          |
          +---> if attempts >= max_attempts:
                  FAILED (moved to ahg_queue_failed, chain cancelled)
```

### Backoff Strategies

| Strategy | Delays |
|----------|--------|
| none | 0s, 0s, 0s |
| linear | 30s, 60s, 90s |
| exponential | 20s, 40s, 80s, 160s... (capped at 1hr) |

---

## 6. File Reference

### Framework (atom-framework/)

| File | Lines | Purpose |
|------|-------|---------|
| `database/migrations/2026_03_01_create_queue_tables.sql` | 140 | 5 table DDL |
| `src/Contracts/QueueJobInterface.php` | 33 | Handler interface |
| `src/Services/QueueJobContext.php` | 72 | Progress/logging context |
| `src/Services/QueueService.php` | 1291 | Core service |
| `src/Services/QueueJobRegistry.php` | 80 | Static handler registry |
| `src/Services/QueueCliTaskHandler.php` | 84 | CLI task bridge |
| `src/Console/Commands/Jobs/QueueWorkCommand.php` | 219 | Worker daemon |
| `src/Console/Commands/Jobs/QueueStatusCommand.php` | 94 | Status command |
| `src/Console/Commands/Jobs/QueueRetryCommand.php` | 71 | Retry command |
| `src/Console/Commands/Jobs/QueueFailedCommand.php` | 84 | Failed list/flush |
| `src/Console/Commands/Jobs/QueueCleanupCommand.php` | 52 | Cleanup command |
| `packaging/templates/systemd/atom-queue-worker@.service` | 20 | systemd template |

### Plugins (atom-ahg-plugins/)

| File | Change | Purpose |
|------|--------|---------|
| `ahgJobsManagePlugin/config/*Configuration*` | Modified | Added queue routes |
| `ahgJobsManagePlugin/modules/*/actions.class.php` | Modified | Added queue actions |
| `ahgJobsManagePlugin/lib/Services/QueueJobsService.php` | New | Admin query service |
| `ahgJobsManagePlugin/modules/*/templates/queue*.php` | New (3) | Queue UI templates |
| `ahgIngestPlugin/*/actions.class.php` | Modified | dispatch() + fallback |
| `ahgIngestPlugin/config/*Configuration*` | Modified | Handler registration |
| `ahgPortableExportPlugin/*/actions.class.php` | Modified | dispatch() + fallback |
| `ahgPortableExportPlugin/config/*Configuration*` | Modified | Handler registration |
| `ahgAIPlugin/config/*Configuration*` | Modified | Handler registration |
| `ahgSettingsPlugin/*/cronJobsAction*` | Modified | Queue category + entries |

---

*AtoM Heratio is developed by The Archive and Heritage Group (Pty) Ltd for GLAM and DAM institutions internationally.*
