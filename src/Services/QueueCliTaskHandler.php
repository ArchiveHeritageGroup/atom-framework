<?php

namespace AtomFramework\Services;

use AtomFramework\Contracts\QueueJobInterface;

/**
 * Bridge handler that wraps existing Symfony CLI tasks.
 *
 * Runs `php symfony <task> <args>` via exec(), allowing plugins to queue
 * existing CLI tasks without rewriting them as QueueJobInterface implementations.
 *
 * Expected payload keys:
 *   'task'    => 'ingest:commit'          (required)
 *   'args'    => '--job-id=123'           (optional, string or array)
 *   'timeout' => 600                      (optional, seconds)
 */
class QueueCliTaskHandler implements QueueJobInterface
{
    public function handle(array $payload, QueueJobContext $context): array
    {
        $task = $payload['task'] ?? null;
        if (empty($task)) {
            return ['success' => false, 'error' => 'No task specified in payload'];
        }

        $args = $payload['args'] ?? '';
        if (is_array($args)) {
            $args = implode(' ', array_map('escapeshellarg', $args));
        }

        $atomRoot = defined('ATOM_ROOT')
            ? ATOM_ROOT
            : '/usr/share/nginx/archive';

        $cmd = sprintf(
            'php %s/symfony %s %s 2>&1',
            escapeshellarg($atomRoot),
            escapeshellarg($task),
            $args
        );

        $context->log('Executing CLI task', ['command' => $cmd]);
        $context->progress(0, 1, 'Running ' . $task);

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $outputStr = implode("\n", $output);

        $context->progress(1, 1, 'Completed ' . $task);

        if ($exitCode !== 0) {
            $context->log('CLI task failed', [
                'exit_code' => $exitCode,
                'output' => mb_substr($outputStr, 0, 2000),
            ]);

            throw new \RuntimeException(
                "CLI task '{$task}' exited with code {$exitCode}: "
                . mb_substr($outputStr, 0, 500)
            );
        }

        $context->log('CLI task completed', ['exit_code' => 0]);

        return [
            'success' => true,
            'exit_code' => $exitCode,
            'output' => mb_substr($outputStr, 0, 5000),
        ];
    }

    public function maxAttempts(): int
    {
        return 1;
    }

    public function timeout(): int
    {
        return 3600; // 1 hour default for CLI tasks
    }
}
