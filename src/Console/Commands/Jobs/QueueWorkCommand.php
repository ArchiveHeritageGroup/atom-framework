<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Services\QueueJobContext;
use AtomFramework\Services\QueueJobRegistry;
use AtomFramework\Services\QueueService;
use AtomExtensions\Database\DatabaseBootstrap;

/**
 * Persistent queue worker daemon.
 *
 * Polls the queue for available jobs and processes them.
 * Designed to run under systemd for automatic restarts.
 *
 * Usage:
 *   php bin/atom queue:work
 *   php bin/atom queue:work --queue=ai,ingest
 *   php bin/atom queue:work --once
 *   php bin/atom queue:work --sleep=3 --max-jobs=100 --max-memory=256
 */
class QueueWorkCommand extends BaseCommand
{
    protected string $name = 'queue:work';
    protected string $description = 'Process jobs from the queue';
    protected string $detailedDescription = <<<'EOF'
Run a persistent queue worker that polls for and processes background jobs.

Options:
  --queue       Comma-separated queue names to process (default: "default")
  --once        Process one job then exit
  --sleep       Seconds to sleep when no jobs available (default: 3)
  --max-jobs    Exit after processing N jobs (0 = unlimited)
  --max-memory  Exit when memory usage exceeds N MB (default: 256)
  --timeout     Per-job timeout in seconds (default: 300)

Examples:
  php bin/atom queue:work                       # Process 'default' queue
  php bin/atom queue:work --queue=ai,ingest     # Multiple queues
  php bin/atom queue:work --once                # One job then exit
EOF;

    private bool $shouldQuit = false;

    protected function configure(): void
    {
        $this->addOption('queue', 'q', 'Queue name(s), comma-separated', 'default');
        $this->addOption('once', null, 'Process one job then exit');
        $this->addOption('sleep', 's', 'Sleep seconds when idle', '3');
        $this->addOption('max-jobs', null, 'Exit after N jobs (0 = unlimited)', '0');
        $this->addOption('max-memory', null, 'Exit at memory limit in MB', '256');
        $this->addOption('timeout', 't', 'Per-job timeout in seconds', '300');
    }

    protected function handle(): int
    {
        DatabaseBootstrap::initializeFromAtom();

        $queues = $this->option('queue') ?: 'default';
        $once = $this->hasOption('once');
        $sleep = max(1, (int) ($this->option('sleep') ?: 3));
        $maxJobs = (int) ($this->option('max-jobs') ?: 0);
        $maxMemory = (int) ($this->option('max-memory') ?: 256);
        $timeout = (int) ($this->option('timeout') ?: 300);

        $workerId = gethostname() . ':' . getmypid();
        $queueService = new QueueService();
        $processed = 0;

        $this->registerSignalHandlers();

        $this->info("Queue worker started [{$workerId}]");
        $this->line("  Queues: {$queues}");
        $this->line("  Sleep: {$sleep}s | Max jobs: " . ($maxJobs ?: 'unlimited') . " | Max memory: {$maxMemory}MB | Timeout: {$timeout}s");
        $this->newline();

        while (!$this->shouldQuit) {
            // Recover stale jobs periodically (every 50th iteration)
            if ($processed % 50 === 0 && $processed > 0) {
                $recovered = $queueService->recoverStale();
                if ($recovered > 0) {
                    $this->comment("Recovered {$recovered} stale job(s)");
                }
            }

            $job = $queueService->reserveNext($queues, $workerId);

            if (!$job) {
                if ($once) {
                    $this->comment('No jobs available, exiting (--once mode)');
                    break;
                }

                sleep($sleep);
                continue;
            }

            $this->processJob($queueService, $job, $workerId, $timeout);
            $processed++;

            // Check exit conditions
            if ($once) {
                break;
            }

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                $this->info("Max jobs reached ({$maxJobs}), exiting");
                break;
            }

            $memoryMb = memory_get_usage(true) / 1024 / 1024;
            if ($memoryMb > $maxMemory) {
                $this->info("Memory limit exceeded (" . round($memoryMb, 1) . "MB > {$maxMemory}MB), exiting");
                break;
            }
        }

        $this->newline();
        $this->success("Worker stopped. Processed {$processed} job(s).");

        return 0;
    }

    /**
     * Process a single job.
     */
    private function processJob(QueueService $queueService, object $job, string $workerId, int $timeout): void
    {
        $jobType = $job->job_type;
        $this->line("[" . date('H:i:s') . "] Processing job #{$job->id} ({$jobType})");

        $queueService->markRunning($job->id, $workerId);

        // Check rate limit
        if ($job->rate_limit_group) {
            if (!$queueService->checkRateLimit($job->rate_limit_group)) {
                // Reschedule for 10 seconds later
                $queueService->markFailed($job->id, 'Rate limited, will retry', 'RATE_LIMITED');
                $this->comment("  Rate limited, rescheduled");

                return;
            }
            $queueService->recordRateUse($job->rate_limit_group);
        }

        // Resolve handler
        $handler = QueueJobRegistry::resolve($jobType);
        if (!$handler) {
            $queueService->markFailed($job->id, "No handler registered for job type: {$jobType}", 'NO_HANDLER');
            $this->error("  No handler for '{$jobType}'");

            return;
        }

        $payload = json_decode($job->payload, true) ?: [];
        $context = new QueueJobContext($job->id, $job->batch_id, $queueService);

        $startTime = microtime(true);

        try {
            // Set alarm for timeout
            if (function_exists('pcntl_alarm') && $timeout > 0) {
                pcntl_alarm($timeout);
            }

            $result = $handler->handle($payload, $context);
            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            // Cancel alarm
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }

            $queueService->markCompleted($job->id, $result, $processingTime);
            $this->success("  Completed in {$processingTime}ms");
        } catch (\Throwable $e) {
            // Cancel alarm
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }

            $processingTime = (int) ((microtime(true) - $startTime) * 1000);
            $queueService->markFailed(
                $job->id,
                $e->getMessage(),
                $e->getCode(),
                $e->getTraceAsString()
            );
            $this->error("  Failed after {$processingTime}ms: " . mb_substr($e->getMessage(), 0, 200));
        }
    }

    /**
     * Register PCNTL signal handlers for graceful shutdown.
     */
    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->comment('SIGTERM received, shutting down gracefully...');
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGINT, function () {
            $this->comment('SIGINT received, shutting down gracefully...');
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGALRM, function () {
            throw new \RuntimeException('Job execution timed out (SIGALRM)');
        });
    }
}
