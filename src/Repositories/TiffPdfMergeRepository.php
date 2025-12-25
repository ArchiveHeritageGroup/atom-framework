<?php

declare(strict_types=1);

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Repository for TIFF to PDF merge operations
 * Uses Laravel Query Builder
 */
class TiffPdfMergeRepository
{
    protected string $jobTable = 'tiff_pdf_merge_job';
    protected string $fileTable = 'tiff_pdf_merge_file';
    protected string $settingsTable = 'tiff_pdf_settings';

    /**
     * Get all settings as key-value array
     */
    public function getSettings(): array
    {
        $settings = [];
        $rows = DB::table($this->settingsTable)->get();

        foreach ($rows as $row) {
            $value = $row->setting_value;

            switch ($row->setting_type) {
                case 'boolean':
                    $value = (bool) (int) $value;
                    break;
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'json':
                    $value = json_decode($value, true) ?? [];
                    break;
            }

            $settings[$row->setting_key] = $value;
        }

        return $settings;
    }

    /**
     * Get a single setting
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $row = DB::table($this->settingsTable)
            ->where('setting_key', $key)
            ->first();

        if (!$row) {
            return $default;
        }

        return match ($row->setting_type) {
            'boolean' => (bool) (int) $row->setting_value,
            'integer' => (int) $row->setting_value,
            'json' => json_decode($row->setting_value, true) ?? $default,
            default => $row->setting_value,
        };
    }

    /**
     * Create a new merge job
     */
    public function createJob(array $data): int
    {
        $defaults = [
            'status' => 'pending',
            'total_files' => 0,
            'processed_files' => 0,
            'pdf_standard' => 'pdfa-2b',
            'compression_quality' => 85,
            'page_size' => 'auto',
            'orientation' => 'auto',
            'dpi' => 300,
            'preserve_originals' => 1,
            'attach_to_record' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $jobData = array_merge($defaults, $data);

        if (isset($jobData['options']) && is_array($jobData['options'])) {
            $jobData['options'] = json_encode($jobData['options']);
        }

        return DB::table($this->jobTable)->insertGetId($jobData);
    }

    /**
     * Get a job by ID
     */
    public function getJob(int $jobId): ?object
    {
        $job = DB::table($this->jobTable)
            ->where('id', $jobId)
            ->first();

        if ($job && $job->options) {
            $job->options = json_decode($job->options, true);
        }

        return $job;
    }

    /**
     * Get jobs with filters
     */
    public function getJobs(array $filters = [], int $limit = 50, int $offset = 0): Collection
    {
        $query = DB::table($this->jobTable)
            ->select([
                "{$this->jobTable}.*",
                'user.username',
            ])
            ->leftJoin('user', "{$this->jobTable}.user_id", '=', 'user.id');

        if (!empty($filters['status'])) {
            $query->where("{$this->jobTable}.status", $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where("{$this->jobTable}.user_id", $filters['user_id']);
        }

        if (!empty($filters['information_object_id'])) {
            $query->where("{$this->jobTable}.information_object_id", $filters['information_object_id']);
        }

        return $query->orderBy("{$this->jobTable}.created_at", 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Update job status
     */
    public function updateJobStatus(int $jobId, string $status, ?string $error = null): bool
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        if ($error !== null) {
            $data['error_message'] = $error;
        }

        return DB::table($this->jobTable)
            ->where('id', $jobId)
            ->update($data) > 0;
    }

    /**
     * Update job output
     */
    public function updateJobOutput(int $jobId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table($this->jobTable)
            ->where('id', $jobId)
            ->update($data) > 0;
    }

    /**
     * Add a file to a merge job
     */
    public function addFile(int $jobId, array $fileData): int
    {
        $defaults = [
            'merge_job_id' => $jobId,
            'status' => 'uploaded',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $data = array_merge($defaults, $fileData);

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        $fileId = DB::table($this->fileTable)->insertGetId($data);

        // Update total files count
        $this->updateFileCount($jobId);

        return $fileId;
    }

    /**
     * Get files for a job
     */
    public function getJobFiles(int $jobId): Collection
    {
        return DB::table($this->fileTable)
            ->where('merge_job_id', $jobId)
            ->orderBy('page_order')
            ->get();
    }

    /**
     * Update file order
     */
    public function updateFileOrder(int $jobId, array $fileOrder): bool
    {
        foreach ($fileOrder as $order => $fileId) {
            DB::table($this->fileTable)
                ->where('id', $fileId)
                ->where('merge_job_id', $jobId)
                ->update(['page_order' => $order]);
        }

        return true;
    }

    /**
     * Delete a file
     */
    public function deleteFile(int $fileId): ?int
    {
        $file = DB::table($this->fileTable)->where('id', $fileId)->first();

        if (!$file) {
            return null;
        }

        $jobId = $file->merge_job_id;

        DB::table($this->fileTable)->where('id', $fileId)->delete();
        $this->updateFileCount($jobId);

        return $jobId;
    }

    /**
     * Delete a job and all files
     */
    public function deleteJob(int $jobId): bool
    {
        DB::table($this->fileTable)->where('merge_job_id', $jobId)->delete();

        return DB::table($this->jobTable)->where('id', $jobId)->delete() > 0;
    }

    /**
     * Get max page order for a job
     */
    public function getMaxPageOrder(int $jobId): int
    {
        $max = DB::table($this->fileTable)
            ->where('merge_job_id', $jobId)
            ->max('page_order');

        return (int) ($max ?? -1);
    }

    /**
     * Update file count
     */
    public function updateFileCount(int $jobId): void
    {
        $count = DB::table($this->fileTable)
            ->where('merge_job_id', $jobId)
            ->count();

        DB::table($this->jobTable)
            ->where('id', $jobId)
            ->update([
                'total_files' => $count,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get pending jobs for a user
     */
    public function getPendingJobs(int $userId, int $limit = 10): Collection
    {
        return DB::table($this->jobTable)
            ->select([
                "{$this->jobTable}.*",
                DB::raw("(SELECT COUNT(*) FROM {$this->fileTable} WHERE merge_job_id = {$this->jobTable}.id) as file_count"),
            ])
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics
     */
    public function getStatistics(?int $userId = null): array
    {
        $baseQuery = DB::table($this->jobTable);

        if ($userId) {
            $baseQuery->where('user_id', $userId);
        }

        return [
            'total_jobs' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'processing' => (clone $baseQuery)->where('status', 'processing')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
            'total_files' => DB::table($this->fileTable)->count(),
        ];
    }
}
