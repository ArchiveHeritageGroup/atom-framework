<?php

/**
 * QubitJob — Compatibility stub.
 *
 * Column constants + runJob() for background job execution.
 * Used by ahgTermTaxonomyPlugin, ahgAccessionManagePlugin, ahgStorageManagePlugin.
 */
if (!class_exists('QubitJob', false)) {
    class QubitJob
    {
        public const ID = 'job.id';
        public const NAME = 'job.name';
        public const USER_ID = 'job.user_id';
        public const OBJECT_ID = 'job.object_id';
        public const STATUS_ID = 'job.status_id';
        public const OUTPUT = 'job.output';
        public const COMPLETED_AT = 'job.completed_at';

        /**
         * Run a background job.
         *
         * In standalone mode, dispatches via the AHG queue system if available,
         * otherwise executes inline.
         *
         * @param string $jobClass Job class name
         * @param array  $options  Job options
         *
         * @return object|null Job record
         */
        public static function runJob(string $jobClass, array $options = [])
        {
            // Try AHG queue dispatch
            if (class_exists(\AtomFramework\Services\QueueService::class)) {
                try {
                    $jobId = \AtomFramework\Services\QueueService::dispatch($jobClass, $options);

                    return (object) ['id' => $jobId, 'name' => $jobClass];
                } catch (\Throwable $e) {
                    // Fall through to nohup
                }
            }

            // Fallback: run via nohup CLI
            $rootDir = \AtomFramework\Services\ConfigService::get('sf_root_dir', '/usr/share/nginx/archive');
            $optJson = base64_encode(json_encode($options));
            $cmd = sprintf(
                'nohup php %s/symfony jobs:worker --job-class=%s --options=%s > /dev/null 2>&1 &',
                escapeshellarg($rootDir),
                escapeshellarg($jobClass),
                escapeshellarg($optJson)
            );
            exec($cmd);

            return null;
        }
    }
}
