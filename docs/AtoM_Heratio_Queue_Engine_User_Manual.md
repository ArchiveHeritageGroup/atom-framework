# AtoM Heratio — Queue Engine User Manual

**Product:** AtoM Heratio Framework v2.8.2
**Component:** Durable Workflow Engine for Background Jobs
**Author:** The Archive and Heritage Group (Pty) Ltd
**Date:** March 2026

---

## Table of Contents

1. [Installation](#1-installation)
2. [Worker Setup](#2-worker-setup)
3. [CLI Commands](#3-cli-commands)
4. [Admin UI](#4-admin-ui)
5. [Troubleshooting](#5-troubleshooting)

---

## 1. Installation

### Database Migration

Run the queue tables migration:

```bash
cd /usr/share/nginx/archive
mysql -u root archive < atom-framework/database/migrations/2026_03_01_create_queue_tables.sql
```

This creates 5 tables:
- `ahg_queue_job` — Central job queue
- `ahg_queue_batch` — Batch job groups
- `ahg_queue_failed` — Archived failed jobs
- `ahg_queue_log` — Audit trail
- `ahg_queue_rate_limit` — Per-group rate limiter

### Plugin Prerequisite

The Admin UI requires `ahgJobsManagePlugin` to be enabled:

```bash
php atom-framework/bin/atom extension:enable ahgJobsManagePlugin
```

---

## 2. Worker Setup

### systemd Service (Recommended for Production)

Copy the service template:

```bash
sudo cp atom-framework/packaging/templates/systemd/atom-queue-worker@.service \
    /etc/systemd/system/
sudo systemctl daemon-reload
```

Start a worker for the `default` queue:

```bash
sudo systemctl enable --now atom-queue-worker@default
```

Start workers for specific queues:

```bash
sudo systemctl enable --now atom-queue-worker@ai
sudo systemctl enable --now atom-queue-worker@ingest
sudo systemctl enable --now atom-queue-worker@export
```

Check worker status:

```bash
sudo systemctl status atom-queue-worker@default
sudo journalctl -u atom-queue-worker@default -f
```

### Manual Worker (Development)

```bash
cd /usr/share/nginx/archive
php atom-framework/bin/atom queue:work --queue=default --sleep=3
```

Process a single job and exit:

```bash
php atom-framework/bin/atom queue:work --once
```

---

## 3. CLI Commands

### queue:work — Worker Daemon

```bash
php atom-framework/bin/atom queue:work [OPTIONS]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--queue=QUEUES` | `default` | Comma-separated queue names |
| `--once` | | Process one job then exit |
| `--sleep=N` | `3` | Seconds to sleep when idle |
| `--max-jobs=N` | `0` | Exit after N jobs (0 = unlimited) |
| `--max-memory=N` | `256` | Exit at memory limit in MB |
| `--timeout=N` | `300` | Per-job timeout in seconds |

### queue:status — Statistics

```bash
php atom-framework/bin/atom queue:status
php atom-framework/bin/atom queue:status --queue=ai
```

Shows per-queue counts (pending, running, completed, failed) and active workers.

### queue:retry — Retry Failed Jobs

```bash
php atom-framework/bin/atom queue:retry 42      # Retry specific failed job
php atom-framework/bin/atom queue:retry --all   # Retry all failed jobs
```

### queue:failed — List/Flush Failed Jobs

```bash
php atom-framework/bin/atom queue:failed            # List failed jobs
php atom-framework/bin/atom queue:failed --flush    # Delete all failed records
php atom-framework/bin/atom queue:failed --limit=50 # Show more entries
```

### queue:cleanup — Purge Old Data

```bash
php atom-framework/bin/atom queue:cleanup           # Purge >30 days old
php atom-framework/bin/atom queue:cleanup --days=7  # Purge >7 days old
```

### Recommended Cron Job

```bash
# Daily cleanup at 3am
0 3 * * * cd /usr/share/nginx/archive && php atom-framework/bin/atom queue:cleanup --days=30 >> /var/log/atom/queue-cleanup.log 2>&1
```

---

## 4. Admin UI

### Accessing the Queue Dashboard

Navigate to: **Admin > Queue** (`/admin/queue`)

Requires administrator privileges.

### Queue Browse

- Filter by queue name, status, or job type
- Status badges: pending (grey), running (blue), completed (green), failed (red), cancelled (yellow)
- Progress bars for jobs with progress tracking
- Retry button for failed jobs
- Cancel button for pending/running jobs
- Auto-refresh when active jobs are present

### Job Detail

Click a job ID to see:
- Full job metadata (type, queue, priority, worker, attempts)
- Progress bar with status message
- Error details with stack trace (for failed jobs)
- Payload and result data (JSON)
- Event log timeline

### Batches

Navigate to: `/admin/queue/batches`
- View batch progress with aggregate bars
- Link to batch jobs
- Cancel running batches

---

## 5. Troubleshooting

### Worker Not Processing Jobs

1. Check worker is running:
   ```bash
   sudo systemctl status atom-queue-worker@default
   ```

2. Check queue has pending jobs:
   ```bash
   php atom-framework/bin/atom queue:status
   ```

3. Check job has a registered handler:
   ```bash
   # If queue:work shows "No handler for 'xxx'" errors,
   # the plugin that registers the handler may not be enabled
   ```

### Jobs Stuck in "reserved" Status

Jobs reserved for more than 10 minutes are automatically recovered by the worker. To manually recover:

```bash
# The worker automatically calls recoverStale() every 50 jobs
# For immediate recovery, restart the worker:
sudo systemctl restart atom-queue-worker@default
```

### Memory Issues

If the worker exits due to memory:
- systemd automatically restarts it (RestartSec=5)
- Reduce `--max-memory` if needed
- Check for memory leaks in job handlers

### Failed Jobs Not Retrying

- Check `attempt_count` vs `max_attempts` on the job
- Jobs at max attempts move to `ahg_queue_failed` table
- Use `queue:retry` or the Admin UI to manually retry

### Legacy Fallback

If the queue worker is not running, plugins fall back to `nohup` process launching automatically. No configuration needed.

---

*AtoM Heratio is developed by The Archive and Heritage Group (Pty) Ltd for GLAM and DAM institutions internationally.*
