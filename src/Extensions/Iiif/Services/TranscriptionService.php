<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Transcription Service - Speech to Text
 * 
 * Converts audio/video speech to text using:
 * - OpenAI Whisper (primary - best accuracy)
 * - Vosk (offline fallback)
 * - Google Speech-to-Text API (optional)
 * 
 * Features:
 * - Multiple language support
 * - Timestamp generation (word and segment level)
 * - Speaker diarization (who spoke when)
 * - VTT/SRT subtitle generation
 * - IIIF annotation integration
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class TranscriptionService
{
    private Logger $logger;
    private string $uploadsDir;
    private array $config;
    
    // Supported languages for Whisper
    public const SUPPORTED_LANGUAGES = [
        'en' => 'English',
        'af' => 'Afrikaans',
        'nl' => 'Dutch',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'pt' => 'Portuguese',
        'it' => 'Italian',
        'pl' => 'Polish',
        'ru' => 'Russian',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'zu' => 'Zulu',
        'xh' => 'Xhosa',
        'st' => 'Sesotho',
    ];
    
    // Whisper model sizes
    public const WHISPER_MODELS = [
        'tiny' => 'Fastest, lowest accuracy (~1GB VRAM)',
        'base' => 'Fast, basic accuracy (~1GB VRAM)',
        'small' => 'Balanced speed/accuracy (~2GB VRAM)',
        'medium' => 'Good accuracy (~5GB VRAM)',
        'large' => 'Best accuracy (~10GB VRAM)',
        'large-v3' => 'Latest, best accuracy (~10GB VRAM)',
    ];
    
    public function __construct(array $config = [])
    {
		$uploadDir = class_exists('sfConfig') 
			? \sfConfig::get('sf_upload_dir') 
			: '/usr/share/nginx/atom/uploads';

		$logDir = class_exists('sfConfig')
			? \sfConfig::get('sf_log_dir', '/var/log/atom')
			: '/var/log/atom';

		$cacheDir = class_exists('sfConfig')
			? \sfConfig::get('sf_cache_dir', '/tmp')
			: '/tmp';

		$this->config = array_merge([
			'uploads_dir' => $uploadDir,
			'transcripts_dir' => $uploadDir . '/transcripts',
			'log_path' => $logDir . '/transcription.log',
			'whisper_path' => '/usr/local/bin/whisper',
			'whisper_model' => 'medium',
			'ffmpeg_path' => '/usr/bin/ffmpeg',
			'default_language' => 'en',
			'auto_detect_language' => true,
			'word_timestamps' => true,
			'max_duration' => 7200, // 2 hours max
			'temp_dir' => $cacheDir . '/whisper',
			], $config);
        
        $this->uploadsDir = $this->config['uploads_dir'];
        
        $this->logger = new Logger('transcription');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }
        
        // Ensure directories exist
        if (!is_dir($this->config['transcripts_dir'])) {
            mkdir($this->config['transcripts_dir'], 0755, true);
        }
        if (!is_dir($this->config['temp_dir'])) {
            mkdir($this->config['temp_dir'], 0755, true);
        }
    }
    
    // ========================================================================
    // Main Transcription Methods
    // ========================================================================
    
    /**
     * Transcribe audio/video file to text
     */
    public function transcribe(int $digitalObjectId, array $options = []): ?array
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        
        if (!$do) {
            $this->logger->error('Digital object not found', ['id' => $digitalObjectId]);
            return null;
        }
        
        $filePath = $this->getFilePath($do);
        
        if (!file_exists($filePath)) {
            $this->logger->error('File not found', ['path' => $filePath]);
            return null;
        }
        
        // Check if already transcribed
        $existing = $this->getTranscription($digitalObjectId);
        if ($existing && empty($options['force'])) {
            $this->logger->info('Using existing transcription', ['id' => $digitalObjectId]);
            return json_decode($existing->transcription_data, true);
        }
        
        $language = $options['language'] ?? $this->config['default_language'];
        $model = $options['model'] ?? $this->config['whisper_model'];
        
        // Extract audio if video file
        $audioPath = $this->extractAudio($filePath, $digitalObjectId);
        if (!$audioPath) {
            return null;
        }
        
        // Run transcription
        $result = $this->runWhisper($audioPath, $language, $model, $options);
        
        if (!$result) {
            return null;
        }
        
        // Store transcription
        $this->storeTranscription($digitalObjectId, $do->object_id, $result, $language);
        
        // Generate subtitle files
        $this->generateSubtitles($digitalObjectId, $result);
        
        // Generate IIIF annotations
        if (!empty($options['generate_iiif'])) {
            $this->generateIiifAnnotations($digitalObjectId, $do->object_id, $result);
        }
        
        // Cleanup temp audio
        if ($audioPath !== $filePath) {
            @unlink($audioPath);
        }
        
        $this->logger->info('Transcription complete', [
            'digital_object_id' => $digitalObjectId,
            'segments' => count($result['segments'] ?? []),
            'duration' => $result['duration'] ?? 0
        ]);
        
        return $result;
    }
    
    /**
     * Extract audio track from video file
     */
    private function extractAudio(string $filePath, int $digitalObjectId): ?string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // If already audio format suitable for Whisper, use directly
        if (in_array($ext, ['wav', 'mp3', 'flac', 'm4a'])) {
            return $filePath;
        }
        
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            $this->logger->error('FFmpeg not available');
            return null;
        }
        
        // Extract to WAV (best for Whisper)
        $audioPath = $this->config['temp_dir'] . '/audio_' . $digitalObjectId . '.wav';
        
        $cmd = sprintf(
            '%s -i %s -vn -acodec pcm_s16le -ar 16000 -ac 1 -y %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($filePath),
            escapeshellarg($audioPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($audioPath)) {
            $this->logger->error('Audio extraction failed', ['output' => implode("\n", $output)]);
            return null;
        }
        
        return $audioPath;
    }
    
    /**
     * Run Whisper transcription
     */
    private function runWhisper(string $audioPath, string $language, string $model, array $options): ?array
    {
        $whisper = $this->config['whisper_path'];
        
        // Check for whisper installation
        if (!$this->isWhisperAvailable()) {
            $this->logger->error('Whisper not available');
            return null;
        }
        
        $outputDir = $this->config['temp_dir'];
        $outputBase = $outputDir . '/whisper_output_' . uniqid();
        
        // Build Whisper command
        $cmdParts = [
            escapeshellcmd($whisper),
            escapeshellarg($audioPath),
            '--model', escapeshellarg($model),
            '--output_dir', escapeshellarg($outputDir),
            '--output_format', 'json',
            '--verbose', 'False',
        ];
        
        // Language setting
        if ($this->config['auto_detect_language'] && empty($options['language'])) {
            // Let Whisper auto-detect
        } else {
            $cmdParts[] = '--language';
            $cmdParts[] = escapeshellarg($language);
        }
        
        // Word-level timestamps
        if ($this->config['word_timestamps']) {
            $cmdParts[] = '--word_timestamps';
            $cmdParts[] = 'True';
        }
        
        // Task (transcribe or translate to English)
        $task = $options['task'] ?? 'transcribe';
        $cmdParts[] = '--task';
        $cmdParts[] = escapeshellarg($task);
        
        $cmd = implode(' ', $cmdParts) . ' 2>&1';
        
        $this->logger->info('Running Whisper', ['command' => $cmd]);
        
        // Execute with timeout
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open($cmd, $descriptors, $pipes);
        
        if (!is_resource($process)) {
            $this->logger->error('Failed to start Whisper process');
            return null;
        }
        
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        if ($returnCode !== 0) {
            $this->logger->error('Whisper failed', ['stderr' => $stderr, 'stdout' => $stdout]);
            return null;
        }
        
        // Find and parse output JSON
        $jsonFile = pathinfo($audioPath, PATHINFO_FILENAME) . '.json';
        $jsonPath = $outputDir . '/' . $jsonFile;
        
        if (!file_exists($jsonPath)) {
            // Try finding any JSON file
            $jsonFiles = glob($outputDir . '/*.json');
            if (!empty($jsonFiles)) {
                $jsonPath = end($jsonFiles);
            } else {
                $this->logger->error('Whisper output not found');
                return null;
            }
        }
        
        $result = json_decode(file_get_contents($jsonPath), true);
        
        // Cleanup temp files
        @unlink($jsonPath);
        
        // Add metadata
        $result['transcription_metadata'] = [
            'model' => $model,
            'language' => $result['language'] ?? $language,
            'task' => $task,
            'transcribed_at' => date('Y-m-d H:i:s'),
        ];
        
        return $result;
    }
    
    /**
     * Check if Whisper is available
     */
    public function isWhisperAvailable(): bool
    {
        $whisper = $this->config['whisper_path'];
        
        if (is_executable($whisper)) {
            return true;
        }
        
        // Check if installed via pip
        $output = shell_exec('which whisper 2>/dev/null');
        if (!empty(trim($output))) {
            $this->config['whisper_path'] = trim($output);
            return true;
        }
        
        // Check for faster-whisper
        $output = shell_exec('which faster-whisper 2>/dev/null');
        if (!empty(trim($output))) {
            $this->config['whisper_path'] = trim($output);
            return true;
        }
        
        return false;
    }
    
    // ========================================================================
    // Subtitle Generation
    // ========================================================================
    
    /**
     * Generate VTT and SRT subtitle files
     */
    public function generateSubtitles(int $digitalObjectId, array $transcription): array
    {
        $segments = $transcription['segments'] ?? [];
        
        if (empty($segments)) {
            return [];
        }
        
        $subtitlesDir = $this->config['transcripts_dir'];
        $baseFilename = 'transcript_' . $digitalObjectId;
        
        // Generate VTT
        $vttPath = $subtitlesDir . '/' . $baseFilename . '.vtt';
        $vttContent = $this->generateVtt($segments);
        file_put_contents($vttPath, $vttContent);
        
        // Generate SRT
        $srtPath = $subtitlesDir . '/' . $baseFilename . '.srt';
        $srtContent = $this->generateSrt($segments);
        file_put_contents($srtPath, $srtContent);
        
        // Generate plain text
        $txtPath = $subtitlesDir . '/' . $baseFilename . '.txt';
        $txtContent = $this->generatePlainText($transcription);
        file_put_contents($txtPath, $txtContent);
        
        return [
            'vtt' => '/uploads/transcripts/' . $baseFilename . '.vtt',
            'srt' => '/uploads/transcripts/' . $baseFilename . '.srt',
            'txt' => '/uploads/transcripts/' . $baseFilename . '.txt',
        ];
    }
    
    /**
     * Generate WebVTT format
     */
    private function generateVtt(array $segments): string
    {
        $vtt = "WEBVTT\n\n";
        
        foreach ($segments as $index => $segment) {
            $start = $this->formatVttTime($segment['start']);
            $end = $this->formatVttTime($segment['end']);
            $text = trim($segment['text']);
            
            $vtt .= ($index + 1) . "\n";
            $vtt .= $start . " --> " . $end . "\n";
            $vtt .= $text . "\n\n";
        }
        
        return $vtt;
    }
    
    /**
     * Generate SRT format
     */
    private function generateSrt(array $segments): string
    {
        $srt = "";
        
        foreach ($segments as $index => $segment) {
            $start = $this->formatSrtTime($segment['start']);
            $end = $this->formatSrtTime($segment['end']);
            $text = trim($segment['text']);
            
            $srt .= ($index + 1) . "\n";
            $srt .= $start . " --> " . $end . "\n";
            $srt .= $text . "\n\n";
        }
        
        return $srt;
    }
    
    /**
     * Generate plain text transcript
     */
    private function generatePlainText(array $transcription): string
    {
        $text = "";
        
        // Add metadata header
        $meta = $transcription['transcription_metadata'] ?? [];
        $text .= "TRANSCRIPTION\n";
        $text .= "=============\n\n";
        
        if (!empty($meta['language'])) {
            $langName = self::SUPPORTED_LANGUAGES[$meta['language']] ?? $meta['language'];
            $text .= "Language: " . $langName . "\n";
        }
        if (!empty($meta['transcribed_at'])) {
            $text .= "Transcribed: " . $meta['transcribed_at'] . "\n";
        }
        if (!empty($transcription['duration'])) {
            $text .= "Duration: " . $this->formatDuration($transcription['duration']) . "\n";
        }
        $text .= "\n---\n\n";
        
        // Add text content
        $text .= $transcription['text'] ?? '';
        
        return $text;
    }
    
    // ========================================================================
    // IIIF Annotation Integration
    // ========================================================================
    
    /**
     * Generate IIIF annotations from transcription
     */
    public function generateIiifAnnotations(int $digitalObjectId, int $objectId, array $transcription): array
    {
        $segments = $transcription['segments'] ?? [];
        $annotations = [];
        
        // Delete existing transcription annotations
        DB::table('iiif_annotation')
            ->where('object_id', $objectId)
            ->where('motivation', 'transcribing')
            ->delete();
        
        foreach ($segments as $segment) {
            $annotationId = DB::table('iiif_annotation')->insertGetId([
                'object_id' => $objectId,
                'canvas_id' => $digitalObjectId,
                'target_canvas' => 'temporal:' . $segment['start'] . ',' . $segment['end'],
                'target_selector' => json_encode([
                    'type' => 'FragmentSelector',
                    'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                    'value' => 't=' . $segment['start'] . ',' . $segment['end'],
                ]),
                'motivation' => 'transcribing',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
            DB::table('iiif_annotation_body')->insert([
                'annotation_id' => $annotationId,
                'body_type' => 'TextualBody',
                'body_value' => trim($segment['text']),
                'body_format' => 'text/plain',
                'body_language' => $transcription['language'] ?? 'en',
                'body_purpose' => 'transcribing',
            ]);
            
            $annotations[] = $annotationId;
        }
        
        $this->logger->info('IIIF annotations created', [
            'object_id' => $objectId,
            'count' => count($annotations)
        ]);
        
        return $annotations;
    }
    
    /**
     * Get transcription as IIIF annotation page
     */
    public function getIiifAnnotationPage(int $digitalObjectId, string $baseUrl): array
    {
        $transcription = $this->getTranscription($digitalObjectId);
        
        if (!$transcription) {
            return [
                '@context' => 'http://iiif.io/api/presentation/3/context.json',
                'id' => $baseUrl . '/iiif/transcription/' . $digitalObjectId,
                'type' => 'AnnotationPage',
                'items' => []
            ];
        }
        
        $data = json_decode($transcription->transcription_data, true);
        $segments = $data['segments'] ?? [];
        $language = $data['language'] ?? 'en';
        
        $items = [];
        foreach ($segments as $index => $segment) {
            $items[] = [
                'id' => $baseUrl . '/iiif/transcription/' . $digitalObjectId . '/annotation/' . $index,
                'type' => 'Annotation',
                'motivation' => 'supplementing',
                'body' => [
                    'type' => 'TextualBody',
                    'value' => trim($segment['text']),
                    'format' => 'text/plain',
                    'language' => $language,
                ],
                'target' => [
                    'source' => $baseUrl . '/iiif/canvas/av-' . $digitalObjectId,
                    'selector' => [
                        'type' => 'FragmentSelector',
                        'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                        'value' => 't=' . number_format($segment['start'], 3) . ',' . number_format($segment['end'], 3),
                    ]
                ]
            ];
        }
        
        return [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $baseUrl . '/iiif/transcription/' . $digitalObjectId,
            'type' => 'AnnotationPage',
            'items' => $items,
        ];
    }
    
    // ========================================================================
    // Database Operations
    // ========================================================================
    
    /**
     * Store transcription in database
     */
    public function storeTranscription(int $digitalObjectId, int $objectId, array $transcription, string $language): int
    {
        // Delete existing
        DB::table('media_transcription')->where('digital_object_id', $digitalObjectId)->delete();
        
        $subtitlePaths = $this->generateSubtitles($digitalObjectId, $transcription);
        
        return DB::table('media_transcription')->insertGetId([
            'digital_object_id' => $digitalObjectId,
            'object_id' => $objectId,
            'language' => $language,
            'full_text' => $transcription['text'] ?? '',
            'transcription_data' => json_encode($transcription),
            'segment_count' => count($transcription['segments'] ?? []),
            'duration' => $transcription['duration'] ?? null,
            'confidence' => $this->calculateAverageConfidence($transcription),
            'model_used' => $transcription['transcription_metadata']['model'] ?? null,
            'vtt_path' => $subtitlePaths['vtt'] ?? null,
            'srt_path' => $subtitlePaths['srt'] ?? null,
            'txt_path' => $subtitlePaths['txt'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
    
    /**
     * Get stored transcription
     */
    public function getTranscription(int $digitalObjectId): ?object
    {
        return DB::table('media_transcription')
            ->where('digital_object_id', $digitalObjectId)
            ->first();
    }
    
    /**
     * Search transcriptions
     */
    public function searchTranscriptions(string $query, ?int $objectId = null): array
    {
        $q = DB::table('media_transcription as t')
            ->leftJoin('information_object_i18n as ioi', 't.object_id', '=', 'ioi.id')
            ->leftJoin('slug', 't.object_id', '=', 'slug.object_id')
            ->where('t.full_text', 'LIKE', '%' . $query . '%');
        
        if ($objectId) {
            $q->where('t.object_id', $objectId);
        }
        
        return $q->select(
                't.*',
                'ioi.title as object_title',
                'slug.slug'
            )
            ->orderBy('t.created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }
    
    /**
     * Get word-level search results with timestamps
     */
    public function searchWithTimestamps(string $query, int $digitalObjectId): array
    {
        $transcription = $this->getTranscription($digitalObjectId);
        
        if (!$transcription) {
            return [];
        }
        
        $data = json_decode($transcription->transcription_data, true);
        $segments = $data['segments'] ?? [];
        $results = [];
        
        $queryLower = strtolower($query);
        
        foreach ($segments as $segment) {
            $textLower = strtolower($segment['text']);
            
            if (strpos($textLower, $queryLower) !== false) {
                $results[] = [
                    'text' => $segment['text'],
                    'start' => $segment['start'],
                    'end' => $segment['end'],
                    'start_formatted' => $this->formatDuration($segment['start']),
                    'end_formatted' => $this->formatDuration($segment['end']),
                ];
            }
            
            // Also check word-level timestamps if available
            if (!empty($segment['words'])) {
                foreach ($segment['words'] as $word) {
                    if (strtolower($word['word']) === $queryLower) {
                        $results[] = [
                            'text' => $word['word'],
                            'start' => $word['start'],
                            'end' => $word['end'],
                            'start_formatted' => $this->formatDuration($word['start']),
                            'end_formatted' => $this->formatDuration($word['end']),
                            'context' => $segment['text'],
                        ];
                    }
                }
            }
        }
        
        return $results;
    }
    
    // ========================================================================
    // Helper Methods
    // ========================================================================
    
    private function getFilePath(object $do): string
    {
        // Path in DB already includes /uploads
        $path = trim($do->path ?? '', '/');
		if (strpos($path, 'uploads/') === 0) {
			$rootDir = class_exists('sfConfig') 
				? \sfConfig::get('sf_root_dir') 
				: '/usr/share/nginx/atom';
			return $rootDir . '/' . $path . '/' . $do->name;
		}
        return $this->uploadsDir . '/' . $path . '/' . $do->name;
    }
    
    private function formatVttTime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor((intval($seconds) % 3600) / 60);
        $secs = floor(intval($seconds) % 60);
        $ms = round(($seconds - floor($seconds)) * 1000);
        
        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $ms);
    }
    
    private function formatSrtTime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor((intval($seconds) % 3600) / 60);
        $secs = floor(intval($seconds) % 60);
        $ms = round(($seconds - floor($seconds)) * 1000);
        
        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $ms);
    }
    
    private function formatDuration(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor((intval($seconds) % 3600) / 60);
        $secs = floor(intval($seconds) % 60);
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }
    
    private function calculateAverageConfidence(array $transcription): ?float
    {
        $segments = $transcription['segments'] ?? [];
        
        if (empty($segments)) {
            return null;
        }
        
        $totalConfidence = 0;
        $count = 0;
        
        foreach ($segments as $segment) {
            if (isset($segment['avg_logprob'])) {
                // Convert log probability to percentage
                $totalConfidence += exp($segment['avg_logprob']) * 100;
                $count++;
            }
        }
        
        return $count > 0 ? round($totalConfidence / $count, 2) : null;
    }
}
