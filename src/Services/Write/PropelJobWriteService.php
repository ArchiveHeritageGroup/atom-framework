<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Job write operations via QubitJob.
 *
 * Uses Propel (QubitJob) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode,
 * handling the AtoM entity inheritance chain:
 *   object -> job
 */
class PropelJobWriteService implements JobWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitJob', false)
            || class_exists('QubitJob');
    }

    public function createJob(array $data): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateJob($data);
        }

        return $this->dbCreateJob($data);
    }

    public function updateJobStatus(int $jobId, int $statusId): void
    {
        if ($this->hasPropel) {
            $this->propelUpdateJobStatus($jobId, $statusId);

            return;
        }

        $this->dbUpdateJobStatus($jobId, $statusId);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateJob(array $data): int
    {
        $job = new \QubitJob();

        if (isset($data['name'])) {
            $job->name = $data['name'];
        }
        if (isset($data['user_id'])) {
            $job->userId = $data['user_id'];
        }
        if (isset($data['status_id'])) {
            $job->statusId = $data['status_id'];
        }
        if (isset($data['object_id'])) {
            $job->objectId = $data['object_id'];
        }
        if (isset($data['download_path'])) {
            $job->downloadPath = $data['download_path'];
        }
        if (isset($data['output'])) {
            $job->output = $data['output'];
        }

        $job->save();

        return $job->id;
    }

    private function propelUpdateJobStatus(int $jobId, int $statusId): void
    {
        $job = \QubitJob::getById($jobId);
        if (null === $job) {
            return;
        }

        $job->statusId = $statusId;
        $job->save();
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateJob(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        // 1. Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitJob',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Insert into job table
        DB::table('job')->insert([
            'id' => $objectId,
            'name' => $data['name'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'object_id' => $data['object_id'] ?? null,
            'download_path' => $data['download_path'] ?? null,
            'output' => $data['output'] ?? null,
        ]);

        return $objectId;
    }

    private function dbUpdateJobStatus(int $jobId, int $statusId): void
    {
        DB::table('job')
            ->where('id', $jobId)
            ->update(['status_id' => $statusId]);

        DB::table('object')
            ->where('id', $jobId)
            ->update(['updated_at' => date('Y-m-d H:i:s')]);
    }
}
