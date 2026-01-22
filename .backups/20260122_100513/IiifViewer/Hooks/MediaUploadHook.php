<?php

/**
 * AtoM Digital Object Upload Hook
 * 
 * Hook into AtoM's digital object upload workflow to automatically:
 * - Generate thumbnails and previews
 * - Extract metadata
 * - Create waveforms
 * 
 * Installation:
 * 1. Copy to: /usr/share/nginx/archive/plugins/ahgThemeB5Plugin/lib/
 * 2. Add to QubitDigitalObject::save() or use event listener
 * 
 * Usage in controller/action:
 *   require_once sfConfig::get('sf_plugins_dir').'/ahgThemeB5Plugin/lib/MediaUploadHook.php';
 *   MediaUploadHook::processDigitalObject($digitalObject);
 * 
 * Or via job queue:
 *   MediaUploadHook::queueProcessing($digitalObjectId);
 * 
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */

class MediaUploadHook
{
    private static bool $initialized = false;
    
    // Audio formats to process
    private const AUDIO_FORMATS = ['wav', 'mp3', 'flac', 'ogg', 'oga', 'm4a', 'aac', 'wma', 'aiff', 'aif'];
    
    // Video formats to process
    private const VIDEO_FORMATS = ['mov', 'mp4', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', 'mpeg', 'mpg', '3gp'];
    
    /**
     * Initialize framework if needed
     */
    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        
        // Load atom-framework autoloader if available
        $frameworkAutoload = sfConfig::get('sf_root_dir') . '/atom-framework/vendor/autoload.php';
        if (file_exists($frameworkAutoload)) {
            require_once $frameworkAutoload;
        }
        
        self::$initialized = true;
    }
    
    /**
     * Process a digital object after upload
     * Call this after saving a new digital object
     * 
     * @param QubitDigitalObject|object $digitalObject The digital object
     * @param array $options Processing options
     * @return array Processing results
     */
    public static function processDigitalObject($digitalObject, array $options = []): array
    {
        self::init();
        
        $id = $digitalObject->id;
        $filename = $digitalObject->name;
        
        // Check if media file
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isMedia = in_array($ext, self::AUDIO_FORMATS) || in_array($ext, self::VIDEO_FORMATS);
        
        if (!$isMedia) {
            return ['processed' => false, 'reason' => 'Not a media file'];
        }
        
        // Use processor service
        try {
            $processor = self::getProcessor();
            return $processor->processUpload($id);
        } catch (Exception $e) {
            // Log error but don't break upload
            error_log('MediaUploadHook: ' . $e->getMessage());
            
            return [
                'processed' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Queue processing for async execution
     * Use this for large files or when you don't want to block the upload
     */
    public static function queueProcessing(int $digitalObjectId, array $options = []): bool
    {
        self::init();

        try {
            // Get object info
            $do = \Illuminate\Database\Capsule\Manager::table('digital_object')
                ->where('id', $digitalObjectId)
                ->select('object_id', 'name')
                ->first();

            if (!$do) {
                return false;
            }

            // Check if media file
            $ext = strtolower(pathinfo($do->name, PATHINFO_EXTENSION));
            $isMedia = in_array($ext, self::AUDIO_FORMATS) || in_array($ext, self::VIDEO_FORMATS);

            if (!$isMedia) {
                return false;
            }

            // Insert into processing queue
            \Illuminate\Database\Capsule\Manager::table('media_processing_queue')->insert([
                'digital_object_id' => $digitalObjectId,
                'object_id' => $do->object_id,
                'task_type' => 'full_processing',
                'task_options' => json_encode($options),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return true;

        } catch (Exception $e) {
            error_log('MediaUploadHook queue error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if processing is enabled
     */
    public static function isEnabled(): bool
    {
        try {
            $row = \Illuminate\Database\Capsule\Manager::table('media_processor_settings')
                ->where('setting_key', 'auto_process_enabled')
                ->select('setting_value')
                ->first();

            return $row && filter_var($row->setting_value, FILTER_VALIDATE_BOOLEAN);
        } catch (Exception $e) {
            // Default to enabled
            return true;
        }
    }
    
    /**
     * Get processor instance
     */
    private static function getProcessor()
    {
        // Load settings from database
        $settings = self::loadSettings();
        
        // Framework path
        $frameworkPath = sfConfig::get('sf_root_dir') . '/atom-framework/src/Extensions/IiifViewer';
        
        // Include service class
        require_once $frameworkPath . '/Services/MediaUploadProcessor.php';
        require_once $frameworkPath . '/Services/MediaMetadataService.php';
        
        return new \AtomFramework\Extensions\IiifViewer\Services\MediaUploadProcessor($settings);
    }
    
    /**
     * Load settings from database
     */
    private static function loadSettings(): array
    {
        $settings = [
            'uploads_dir' => sfConfig::get('sf_upload_dir', '/usr/share/nginx/atom/uploads'),
            'derivatives_dir' => sfConfig::get('sf_upload_dir', '/usr/share/nginx/atom/uploads') . '/derivatives',
        ];

        try {
            $rows = \Illuminate\Database\Capsule\Manager::table('media_processor_settings')
                ->select('setting_key', 'setting_value', 'setting_type')
                ->get();

            foreach ($rows as $row) {
                $value = $row->setting_value;

                switch ($row->setting_type) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'float':
                        $value = (float) $value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }

                $settings[$row->setting_key] = $value;
            }
        } catch (Exception $e) {
            // Use defaults
        }

        return $settings;
    }
    
    /**
     * Run pending queue items (call from cron)
     */
    public static function processQueue(int $limit = 10): array
    {
        self::init();

        $results = [];

        try {
            // Get pending items
            $items = \Illuminate\Database\Capsule\Manager::table('media_processing_queue')
                ->where('status', 'pending')
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();

            $processor = self::getProcessor();

            foreach ($items as $item) {
                // Mark as processing
                \Illuminate\Database\Capsule\Manager::table('media_processing_queue')
                    ->where('id', $item->id)
                    ->update([
                        'status' => 'processing',
                        'started_at' => date('Y-m-d H:i:s'),
                    ]);

                try {
                    $result = $processor->processUpload($item->digital_object_id);

                    $status = $result['success'] ? 'completed' : 'failed';
                    $error = $result['error'] ?? null;

                    \Illuminate\Database\Capsule\Manager::table('media_processing_queue')
                        ->where('id', $item->id)
                        ->update([
                            'status' => $status,
                            'error_message' => $error,
                            'completed_at' => date('Y-m-d H:i:s'),
                        ]);

                    $results[$item->id] = $result;

                } catch (Exception $e) {
                    // Mark as failed
                    \Illuminate\Database\Capsule\Manager::table('media_processing_queue')
                        ->where('id', $item->id)
                        ->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'retry_count' => \Illuminate\Database\Capsule\Manager::raw('retry_count + 1'),
                            'completed_at' => date('Y-m-d H:i:s'),
                        ]);

                    $results[$item->id] = ['success' => false, 'error' => $e->getMessage()];
                }
            }

        } catch (Exception $e) {
            error_log('MediaUploadHook queue processing error: ' . $e->getMessage());
        }

        return $results;
    }
}
