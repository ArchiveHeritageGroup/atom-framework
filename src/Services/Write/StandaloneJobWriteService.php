<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone job write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain: object -> job.
 */
class StandaloneJobWriteService implements JobWriteServiceInterface
{
    public function createJob(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return DB::transaction(function () use ($data, $now) {
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitJob',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

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
        });
    }

    public function updateJobStatus(int $jobId, int $statusId): void
    {
        DB::table('job')
            ->where('id', $jobId)
            ->update(['status_id' => $statusId]);

        DB::table('object')
            ->where('id', $jobId)
            ->update(['updated_at' => date('Y-m-d H:i:s')]);
    }
}
