<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Media Upload Processor
 * 
 * Automatically processes media files on upload:
 * - Generates video thumbnails at configurable time points
 * - Creates preview clips/snippets
 * - Extracts audio waveforms
 * - Extracts metadata
 * - Creates poster images
 * 
 * Hook this into AtoM's digital object upload workflow
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class MediaUploadProcessor
{
    private Logger $logger;
    private string $uploadsDir;
    private array $config;
    private MediaMetadataService $metadataService;
    
    // Supported media formats
    private const AUDIO_FORMATS = ['wav', 'mp3', 'flac', 'ogg', 'oga', 'm4a', 'aac', 'wma', 'aiff'];
    private const VIDEO_FORMATS = ['mov', 'mp4', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', 'mpeg', 'mpg'];
    
    public function __construct(array $config = [])
    {
			$uploadDir = class_exists('sfConfig') 
				? \sfConfig::get('sf_upload_dir') 
				: '/usr/share/nginx/atom/uploads';

			$logDir = class_exists('sfConfig')
				? \sfConfig::get('sf_log_dir', '/var/log/atom')
				: '/var/log/atom';

			$this->config = array_merge([
				'uploads_dir' => $uploadDir,
				'derivatives_dir' => $uploadDir . '/derivatives',
				'log_path' => $logDir . '/media-upload.log',
				'ffmpeg_path' => '/usr/bin/ffmpeg',
				'ffprobe_path' => '/usr/bin/ffprobe',
	
            // Thumbnail settings
            'thumbnail_enabled' => true,
            'thumbnail_time' => 5,              // Seconds into video
            'thumbnail_width' => 480,
            'thumbnail_height' => 270,
            'thumbnail_format' => 'jpg',
            
            // Preview clip settings  
            'preview_enabled' => true,
            'preview_start' => 0,               // Start time
            'preview_duration' => 30,           // Duration in seconds
            'preview_format' => 'mp4',
            'preview_video_bitrate' => '1M',
            'preview_audio_bitrate' => '128k',
            
            // Waveform settings
            'waveform_enabled' => true,
            'waveform_width' => 1800,
            'waveform_height' => 140,
            'waveform_color' => '#2980b9',
            
            // Poster settings (for video)
            'poster_enabled' => true,
            'poster_times' => [1, 10, 30],      // Multiple poster options
            'poster_width' => 1280,
            'poster_height' => 720,
            
            // Audio preview settings
            'audio_preview_enabled' => true,
            'audio_preview_duration' => 30,
            'audio_preview_format' => 'mp3',
            'audio_preview_bitrate' => '192k',
            
            // Metadata extraction
            'metadata_enabled' => true,
            
            // Process queue
            'async_processing' => false,        // If true, add to queue instead
        ], $config);
        
        $this->uploadsDir = $this->config['uploads_dir'];
        
        // Ensure directories exist
        $dirs = [
            $this->config['derivatives_dir'],
            $this->config['derivatives_dir'] . '/thumbnails',
            $this->config['derivatives_dir'] . '/previews',
            $this->config['derivatives_dir'] . '/waveforms',
            $this->config['derivatives_dir'] . '/posters',
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        $this->logger = new Logger('media-upload');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }
        
        $this->metadataService = new MediaMetadataService($config);
    }
    
    // ========================================================================
    // Main Processing Entry Point
    // ========================================================================
    
    /**
     * Process a newly uploaded media file
     * Call this after AtoM saves a digital object
     */
    public function processUpload(int $digitalObjectId): array
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        
        if (!$do) {
            $this->logger->error('Digital object not found', ['id' => $digitalObjectId]);
            return ['success' => false, 'error' => 'Digital object not found'];
        }
        
        $filePath = $this->uploadsDir . '/' . trim($do->path ?? '', '/') . '/' . $do->name;
        
        if (!file_exists($filePath)) {
            $this->logger->error('File not found', ['path' => $filePath]);
            return ['success' => false, 'error' => 'File not found'];
        }
        
        $ext = strtolower(pathinfo($do->name, PATHINFO_EXTENSION));
        $isAudio = in_array($ext, self::AUDIO_FORMATS);
        $isVideo = in_array($ext, self::VIDEO_FORMATS);
        
        if (!$isAudio && !$isVideo) {
            return ['success' => false, 'error' => 'Not a media file'];
        }
        
        $results = [
            'success' => true,
            'digital_object_id' => $digitalObjectId,
            'media_type' => $isVideo ? 'video' : 'audio',
            'derivatives' => [],
        ];
        
        $this->logger->info('Processing media upload', [
            'digital_object_id' => $digitalObjectId,
            'media_type' => $results['media_type'],
            'filename' => $do->name
        ]);
        
        // Get duration for intelligent thumbnail timing
        $duration = $this->getMediaDuration($filePath);
        
        if ($isVideo) {
            // Generate video derivatives
            if ($this->config['thumbnail_enabled']) {
                $thumb = $this->generateVideoThumbnail($digitalObjectId, $filePath, $duration);
                if ($thumb) $results['derivatives']['thumbnail'] = $thumb;
            }
            
            if ($this->config['poster_enabled']) {
                $posters = $this->generateVideoPosters($digitalObjectId, $filePath, $duration);
                if ($posters) $results['derivatives']['posters'] = $posters;
            }
            
            if ($this->config['preview_enabled']) {
                $preview = $this->generateVideoPreview($digitalObjectId, $filePath, $duration);
                if ($preview) $results['derivatives']['preview'] = $preview;
            }
        }
        
        if ($isAudio || $isVideo) {
            // Generate waveform for audio (or extract audio track from video)
            if ($this->config['waveform_enabled']) {
                $waveform = $this->generateWaveform($digitalObjectId, $filePath);
                if ($waveform) $results['derivatives']['waveform'] = $waveform;
            }
        }
        
        if ($isAudio && $this->config['audio_preview_enabled']) {
            $preview = $this->generateAudioPreview($digitalObjectId, $filePath, $duration);
            if ($preview) $results['derivatives']['preview'] = $preview;
        }
        
        // Extract metadata
        if ($this->config['metadata_enabled']) {
            $metadata = $this->metadataService->extractMetadata($digitalObjectId);
            if ($metadata) {
                $results['metadata'] = true;
            }
        }
        
        // Store derivatives info
        $this->storeDerivatives($digitalObjectId, $results['derivatives']);
        
        $this->logger->info('Media processing complete', [
            'digital_object_id' => $digitalObjectId,
            'derivatives' => array_keys($results['derivatives'])
        ]);
        
        return $results;
    }
    
    // ========================================================================
    // Video Processing
    // ========================================================================
    
    /**
     * Generate video thumbnail
     */
    public function generateVideoThumbnail(int $digitalObjectId, string $filePath, ?float $duration = null): ?string
    {
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            return null;
        }
        
        // Calculate thumbnail time (use setting, but cap at 90% of duration)
        $thumbTime = $this->config['thumbnail_time'];
        if ($duration && $thumbTime > $duration * 0.9) {
            $thumbTime = max(0, $duration * 0.1);
        }
        
        $thumbFilename = 'thumb_' . $digitalObjectId . '.' . $this->config['thumbnail_format'];
        $thumbPath = $this->config['derivatives_dir'] . '/thumbnails/' . $thumbFilename;
        
        $width = $this->config['thumbnail_width'];
        $height = $this->config['thumbnail_height'];
        
        $cmd = sprintf(
            '%s -y -ss %f -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black" -q:v 2 %s 2>&1',
            escapeshellcmd($ffmpeg),
            $thumbTime,
            escapeshellarg($filePath),
            $width, $height, $width, $height,
            escapeshellarg($thumbPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($thumbPath)) {
            $this->logger->warning('Thumbnail generation failed', ['output' => implode("\n", $output)]);
            return null;
        }
        
        return '/uploads/derivatives/thumbnails/' . $thumbFilename;
    }
    
    /**
     * Generate multiple poster images at different time points
     */
    public function generateVideoPosters(int $digitalObjectId, string $filePath, ?float $duration = null): array
    {
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            return [];
        }
        
        $posters = [];
        $times = $this->config['poster_times'];
        
        // Adjust times based on actual duration
        if ($duration) {
            $times = array_filter($times, fn($t) => $t < $duration);
            if (empty($times)) {
                $times = [$duration * 0.1];
            }
        }
        
        $width = $this->config['poster_width'];
        $height = $this->config['poster_height'];
        
        foreach ($times as $index => $time) {
            $posterFilename = sprintf('poster_%d_%d.jpg', $digitalObjectId, $index);
            $posterPath = $this->config['derivatives_dir'] . '/posters/' . $posterFilename;
            
            $cmd = sprintf(
                '%s -y -ss %f -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease" -q:v 2 %s 2>&1',
                escapeshellcmd($ffmpeg),
                $time,
                escapeshellarg($filePath),
                $width, $height,
                escapeshellarg($posterPath)
            );
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($posterPath)) {
                $posters[] = [
                    'time' => $time,
                    'url' => '/uploads/derivatives/posters/' . $posterFilename,
                ];
            }
        }
        
        return $posters;
    }
    
    /**
     * Generate video preview clip
     */
    public function generateVideoPreview(int $digitalObjectId, string $filePath, ?float $duration = null): ?string
    {
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            return null;
        }
        
        $startTime = $this->config['preview_start'];
        $previewDuration = $this->config['preview_duration'];
        
        // Adjust if file is shorter than preview duration
        if ($duration && $previewDuration > $duration) {
            $previewDuration = $duration;
            $startTime = 0;
        }
        
        $previewFilename = 'preview_' . $digitalObjectId . '.' . $this->config['preview_format'];
        $previewPath = $this->config['derivatives_dir'] . '/previews/' . $previewFilename;
        
        $videoBitrate = $this->config['preview_video_bitrate'];
        $audioBitrate = $this->config['preview_audio_bitrate'];
        
        // Create preview with reduced quality for quick loading
        $cmd = sprintf(
            '%s -y -ss %f -i %s -t %f -vf "scale=640:-2" -c:v libx264 -preset fast -b:v %s -c:a aac -b:a %s -movflags +faststart %s 2>&1',
            escapeshellcmd($ffmpeg),
            $startTime,
            escapeshellarg($filePath),
            $previewDuration,
            $videoBitrate,
            $audioBitrate,
            escapeshellarg($previewPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($previewPath)) {
            $this->logger->warning('Video preview generation failed', ['output' => implode("\n", $output)]);
            return null;
        }
        
        return '/uploads/derivatives/previews/' . $previewFilename;
    }
    
    // ========================================================================
    // Audio Processing
    // ========================================================================
    
    /**
     * Generate waveform image
     */
    public function generateWaveform(int $digitalObjectId, string $filePath): ?string
    {
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            return null;
        }
        
        $waveformFilename = 'waveform_' . $digitalObjectId . '.png';
        $waveformPath = $this->config['derivatives_dir'] . '/waveforms/' . $waveformFilename;
        
        $width = $this->config['waveform_width'];
        $height = $this->config['waveform_height'];
        $color = $this->config['waveform_color'];
        
        // Use showwavespic filter
        $cmd = sprintf(
            '%s -y -i %s -filter_complex "showwavespic=s=%dx%d:colors=%s|%s:split_channels=0" -frames:v 1 %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($filePath),
            $width, $height,
            $color, $color,
            escapeshellarg($waveformPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($waveformPath)) {
            $this->logger->warning('Waveform generation failed', ['output' => implode("\n", $output)]);
            return null;
        }
        
        return '/uploads/derivatives/waveforms/' . $waveformFilename;
    }
    
    /**
     * Generate audio preview clip
     */
    public function generateAudioPreview(int $digitalObjectId, string $filePath, ?float $duration = null): ?string
    {
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            return null;
        }
        
        $previewDuration = $this->config['audio_preview_duration'];
        
        if ($duration && $previewDuration > $duration) {
            $previewDuration = $duration;
        }
        
        $previewFilename = 'preview_' . $digitalObjectId . '.' . $this->config['audio_preview_format'];
        $previewPath = $this->config['derivatives_dir'] . '/previews/' . $previewFilename;
        
        $bitrate = $this->config['audio_preview_bitrate'];
        
        $cmd = sprintf(
            '%s -y -i %s -t %f -c:a libmp3lame -b:a %s %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($filePath),
            $previewDuration,
            $bitrate,
            escapeshellarg($previewPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($previewPath)) {
            $this->logger->warning('Audio preview generation failed', ['output' => implode("\n", $output)]);
            return null;
        }
        
        return '/uploads/derivatives/previews/' . $previewFilename;
    }
    
    // ========================================================================
    // Helper Methods
    // ========================================================================
    
    /**
     * Get media duration using ffprobe
     */
    private function getMediaDuration(string $filePath): ?float
    {
        $ffprobe = $this->config['ffprobe_path'];
        
        if (!is_executable($ffprobe)) {
            return null;
        }
        
        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of csv=p=0 %s 2>&1',
            escapeshellcmd($ffprobe),
            escapeshellarg($filePath)
        );
        
        $output = trim(shell_exec($cmd));
        
        return is_numeric($output) ? (float)$output : null;
    }
    
    /**
     * Store derivative paths in database
     */
    private function storeDerivatives(int $digitalObjectId, array $derivatives): void
    {
        if (empty($derivatives)) {
            return;
        }
        
        // Delete existing
        DB::table('media_derivatives')->where('digital_object_id', $digitalObjectId)->delete();
        
        // Store each derivative
        foreach ($derivatives as $type => $data) {
            if (is_array($data) && isset($data[0])) {
                // Multiple items (e.g., posters)
                foreach ($data as $index => $item) {
                    DB::table('media_derivatives')->insert([
                        'digital_object_id' => $digitalObjectId,
                        'derivative_type' => $type,
                        'derivative_index' => $index,
                        'path' => is_array($item) ? $item['url'] : $item,
                        'metadata' => is_array($item) ? json_encode($item) : null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                // Single item
                DB::table('media_derivatives')->insert([
                    'digital_object_id' => $digitalObjectId,
                    'derivative_type' => $type,
                    'derivative_index' => 0,
                    'path' => is_array($data) ? ($data['url'] ?? $data['path'] ?? null) : $data,
                    'metadata' => is_array($data) ? json_encode($data) : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
    
    /**
     * Get stored derivatives for a digital object
     */
    public function getDerivatives(int $digitalObjectId): array
    {
        $rows = DB::table('media_derivatives')
            ->where('digital_object_id', $digitalObjectId)
            ->orderBy('derivative_type')
            ->orderBy('derivative_index')
            ->get();
        
        $derivatives = [];
        
        foreach ($rows as $row) {
            $type = $row->derivative_type;
            
            if (!isset($derivatives[$type])) {
                $derivatives[$type] = [];
            }
            
            $item = [
                'path' => $row->path,
            ];
            
            if ($row->metadata) {
                $item = array_merge($item, json_decode($row->metadata, true) ?: []);
            }
            
            $derivatives[$type][] = $item;
        }
        
        // Flatten single-item arrays
        foreach ($derivatives as $type => $items) {
            if (count($items) === 1) {
                $derivatives[$type] = $items[0];
            }
        }
        
        return $derivatives;
    }
    
    // ========================================================================
    // Settings Management
    // ========================================================================
    
    /**
     * Load settings from database
     */
    public static function loadSettings(): array
    {
        $settings = [];
        
        $rows = DB::table('media_processor_settings')->get();
        
        foreach ($rows as $row) {
            $value = $row->setting_value;
            
            // Cast to appropriate type
            switch ($row->setting_type) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'float':
                    $value = (float)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $settings[$row->setting_key] = $value;
        }
        
        return $settings;
    }
    
    /**
     * Save a setting
     */
    public static function saveSetting(string $key, $value, string $type = 'string'): void
    {
        // Convert value to string for storage
        if ($type === 'boolean') {
            $value = $value ? '1' : '0';
        } elseif ($type === 'json') {
            $value = json_encode($value);
        } else {
            $value = (string)$value;
        }
        
        DB::table('media_processor_settings')
            ->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'setting_type' => $type,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
    }
}
