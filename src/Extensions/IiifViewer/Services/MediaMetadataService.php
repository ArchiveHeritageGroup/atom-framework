<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Media Metadata Extraction Service
 * 
 * Extracts comprehensive metadata from audio/video files:
 * - Technical metadata (codec, bitrate, duration, channels, etc.)
 * - Embedded metadata (title, artist, album, copyright, etc.)
 * - Stream information (audio/video tracks)
 * - Chapter markers
 * - Waveform data
 * 
 * Supports: WAV, MOV, MP4, MP3, FLAC, OGG, AVI, MKV, WEBM, M4A, AAC
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.1.0
 */
class MediaMetadataService
{
    private Logger $logger;
    private string $uploadsDir;
    private array $config;
    
    // Supported audio formats
    public const AUDIO_FORMATS = ['wav', 'mp3', 'flac', 'ogg', 'oga', 'm4a', 'aac', 'wma', 'aiff', 'aif'];
    
    // Supported video formats
    public const VIDEO_FORMATS = ['mov', 'mp4', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', 'mpeg', 'mpg', '3gp'];
    
    public function __construct(array $config = [])
    {
        // Get upload directory from sfConfig - no hardcoded paths
        $defaultUploadDir = class_exists('sfConfig') 
            ? \sfConfig::get('sf_upload_dir') 
            : '/usr/share/nginx/atom/uploads';
        
        $defaultLogDir = class_exists('sfConfig')
            ? \sfConfig::get('sf_log_dir', '/var/log/atom')
            : '/var/log/atom';
        
        $this->config = array_merge([
            'uploads_dir' => $defaultUploadDir,
            'log_path' => $defaultLogDir . '/media-metadata.log',
            'ffprobe_path' => '/usr/bin/ffprobe',
            'ffmpeg_path' => '/usr/bin/ffmpeg',
            'mediainfo_path' => '/usr/bin/mediainfo',
            'exiftool_path' => '/usr/bin/exiftool',
            'generate_waveform' => true,
            'waveform_width' => 1800,
            'waveform_height' => 140,
        ], $config);
        
        $this->uploadsDir = $this->config['uploads_dir'];
        
        $this->logger = new Logger('media-metadata');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }
    }
    
    // ========================================================================
    // Main Extraction Methods
    // ========================================================================
    
    /**
     * Extract all available metadata from a media file
     */
    public function extractMetadata(int $digitalObjectId): ?array
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
        
        $ext = strtolower(pathinfo($do->name, PATHINFO_EXTENSION));
        $isAudio = in_array($ext, self::AUDIO_FORMATS);
        $isVideo = in_array($ext, self::VIDEO_FORMATS);
        
        if (!$isAudio && !$isVideo) {
            $this->logger->info('Not a media file', ['extension' => $ext]);
            return null;
        }
        
        $metadata = [
            'digital_object_id' => $digitalObjectId,
            'object_id' => $do->object_id,
            'filename' => $do->name,
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'media_type' => $isAudio ? 'audio' : 'video',
            'format' => $ext,
            'extracted_at' => date('Y-m-d H:i:s'),
        ];
        
        // Extract using multiple tools for comprehensive data
        $metadata['ffprobe'] = $this->extractWithFfprobe($filePath);
        $metadata['mediainfo'] = $this->extractWithMediainfo($filePath);
        $metadata['exiftool'] = $this->extractWithExiftool($filePath);
        
        // Consolidate into structured format
        $metadata['consolidated'] = $this->consolidateMetadata($metadata);
        
        // Generate waveform for audio
        if ($isAudio && $this->config['generate_waveform']) {
            $metadata['waveform'] = $this->generateWaveform($filePath, $digitalObjectId);
        }
        
        // Store in database
        $this->storeMetadata($digitalObjectId, $do->object_id, $metadata);
        
        $this->logger->info('Metadata extracted', [
            'digital_object_id' => $digitalObjectId,
            'duration' => $metadata['consolidated']['duration'] ?? 0,
        ]);
        
        return $metadata;
    }
    
    /**
     * Extract metadata using FFprobe
     */
    private function extractWithFfprobe(string $filePath): array
    {
        $ffprobe = $this->config['ffprobe_path'];
        
        if (!is_executable($ffprobe)) {
            return ['error' => 'ffprobe not available'];
        }
        
        $cmd = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams -show_chapters %s 2>&1',
            escapeshellcmd($ffprobe),
            escapeshellarg($filePath)
        );
        
        $output = shell_exec($cmd);
        $data = json_decode($output, true);
        
        if (!$data) {
            return ['error' => 'Failed to parse ffprobe output'];
        }
        
        return $data;
    }
    
    /**
     * Extract metadata using MediaInfo
     */
    private function extractWithMediainfo(string $filePath): array
    {
        $mediainfo = $this->config['mediainfo_path'];
        
        if (!is_executable($mediainfo)) {
            return ['error' => 'mediainfo not available'];
        }
        
        $cmd = sprintf(
            '%s --Output=JSON %s 2>&1',
            escapeshellcmd($mediainfo),
            escapeshellarg($filePath)
        );
        
        $output = shell_exec($cmd);
        $data = json_decode($output, true);
        
        if (!$data) {
            return ['error' => 'Failed to parse mediainfo output'];
        }
        
        return $data;
    }
    
    /**
     * Extract metadata using ExifTool
     */
    private function extractWithExiftool(string $filePath): array
    {
        $exiftool = $this->config['exiftool_path'];
        
        if (!is_executable($exiftool)) {
            return ['error' => 'exiftool not available'];
        }
        
        $cmd = sprintf(
            '%s -json -a -u -g1 %s 2>&1',
            escapeshellcmd($exiftool),
            escapeshellarg($filePath)
        );
        
        $output = shell_exec($cmd);
        $data = json_decode($output, true);
        
        if (!$data || !isset($data[0])) {
            return ['error' => 'Failed to parse exiftool output'];
        }
        
        return $data[0];
    }
    
    /**
     * Consolidate metadata from all sources into structured format
     */
    private function consolidateMetadata(array $rawMetadata): array
    {
        $ffprobe = $rawMetadata['ffprobe'] ?? [];
        $mediainfo = $rawMetadata['mediainfo']['media']['track'] ?? [];
        $exiftool = $rawMetadata['exiftool'] ?? [];
        
        $format = $ffprobe['format'] ?? [];
        $streams = $ffprobe['streams'] ?? [];
        $chapters = $ffprobe['chapters'] ?? [];
        
        // Find audio and video streams
        $audioStream = null;
        $videoStream = null;
        
        foreach ($streams as $stream) {
            if ($stream['codec_type'] === 'audio' && !$audioStream) {
                $audioStream = $stream;
            }
            if ($stream['codec_type'] === 'video' && !$videoStream) {
                $videoStream = $stream;
            }
        }
        
        // Build consolidated metadata
        $consolidated = [
            // Basic info
            'duration' => (float) ($format['duration'] ?? 0),
            'duration_formatted' => $this->formatDuration((float) ($format['duration'] ?? 0)),
            'bitrate' => (int) ($format['bit_rate'] ?? 0),
            'bitrate_formatted' => $this->formatBitrate((int) ($format['bit_rate'] ?? 0)),
            'file_size' => $rawMetadata['file_size'],
            'file_size_formatted' => $this->formatFileSize($rawMetadata['file_size']),
            
            // Container format
            'container_format' => $format['format_name'] ?? null,
            'container_format_long' => $format['format_long_name'] ?? null,
            
            // Audio properties
            'audio' => $audioStream ? [
                'codec' => $audioStream['codec_name'] ?? null,
                'codec_long' => $audioStream['codec_long_name'] ?? null,
                'sample_rate' => (int) ($audioStream['sample_rate'] ?? 0),
                'channels' => (int) ($audioStream['channels'] ?? 0),
                'channel_layout' => $audioStream['channel_layout'] ?? null,
                'bits_per_sample' => (int) ($audioStream['bits_per_sample'] ?? $audioStream['bits_per_raw_sample'] ?? 0),
                'bitrate' => (int) ($audioStream['bit_rate'] ?? 0),
                'stream_index' => $audioStream['index'] ?? 0,
            ] : null,
            
            // Video properties
            'video' => $videoStream ? [
                'codec' => $videoStream['codec_name'] ?? null,
                'codec_long' => $videoStream['codec_long_name'] ?? null,
                'width' => (int) ($videoStream['width'] ?? 0),
                'height' => (int) ($videoStream['height'] ?? 0),
                'aspect_ratio' => $videoStream['display_aspect_ratio'] ?? null,
                'frame_rate' => $this->parseFrameRate($videoStream['r_frame_rate'] ?? '0/1'),
                'bitrate' => (int) ($videoStream['bit_rate'] ?? 0),
                'pixel_format' => $videoStream['pix_fmt'] ?? null,
                'color_space' => $videoStream['color_space'] ?? null,
                'stream_index' => $videoStream['index'] ?? 0,
            ] : null,
            
            // Embedded metadata/tags
            'tags' => $this->extractTags($format, $exiftool, $mediainfo),
            
            // Chapters
            'chapters' => array_map(function ($ch) {
                return [
                    'start' => (float) $ch['start_time'],
                    'end' => (float) $ch['end_time'],
                    'title' => $ch['tags']['title'] ?? null,
                ];
            }, $chapters),
            
            // Stream count
            'stream_count' => [
                'audio' => count(array_filter($streams, fn ($s) => $s['codec_type'] === 'audio')),
                'video' => count(array_filter($streams, fn ($s) => $s['codec_type'] === 'video')),
                'subtitle' => count(array_filter($streams, fn ($s) => $s['codec_type'] === 'subtitle')),
            ],
            
            // All streams for detailed view
            'streams' => $streams,
        ];
        
        // Add WAV-specific metadata
        if ($rawMetadata['format'] === 'wav') {
            $consolidated['wav'] = $this->extractWavMetadata($rawMetadata);
        }
        
        // Add MOV/MP4-specific metadata
        if (in_array($rawMetadata['format'], ['mov', 'mp4', 'm4v', 'm4a'])) {
            $consolidated['quicktime'] = $this->extractQuicktimeMetadata($exiftool, $mediainfo);
        }
        
        return $consolidated;
    }
    
    /**
     * Extract embedded tags/metadata
     */
    private function extractTags(array $format, array $exiftool, array $mediainfo): array
    {
        $tags = [];
        
        // From FFprobe format tags
        $formatTags = $format['tags'] ?? [];
        
        // Standard tags mapping
        $tagMapping = [
            'title' => ['title', 'Title', 'TITLE'],
            'artist' => ['artist', 'Artist', 'ARTIST', 'author', 'Author'],
            'album' => ['album', 'Album', 'ALBUM'],
            'album_artist' => ['album_artist', 'AlbumArtist', 'ALBUMARTIST'],
            'composer' => ['composer', 'Composer', 'COMPOSER'],
            'genre' => ['genre', 'Genre', 'GENRE'],
            'year' => ['year', 'Year', 'date', 'Date', 'DATE', 'creation_time'],
            'track' => ['track', 'Track', 'TRACKNUMBER'],
            'disc' => ['disc', 'Disc', 'DISCNUMBER'],
            'copyright' => ['copyright', 'Copyright', 'COPYRIGHT'],
            'comment' => ['comment', 'Comment', 'COMMENT', 'description', 'Description'],
            'encoder' => ['encoder', 'Encoder', 'encoding_tool', 'software'],
            'language' => ['language', 'Language', 'LANGUAGE'],
            'publisher' => ['publisher', 'Publisher', 'PUBLISHER'],
            'isrc' => ['isrc', 'ISRC'],
            'barcode' => ['barcode', 'Barcode', 'UPC', 'EAN'],
            'bpm' => ['bpm', 'BPM', 'TBPM'],
            'key' => ['key', 'Key', 'initialkey', 'INITIALKEY'],
            'mood' => ['mood', 'Mood', 'MOOD'],
            'rating' => ['rating', 'Rating', 'RATING'],
        ];
        
        foreach ($tagMapping as $key => $sources) {
            foreach ($sources as $source) {
                // Check ffprobe tags
                if (isset($formatTags[$source])) {
                    $tags[$key] = $formatTags[$source];
                    break;
                }
                // Check exiftool
                foreach ($exiftool as $group => $values) {
                    if (is_array($values) && isset($values[$source])) {
                        $tags[$key] = $values[$source];
                        break 2;
                    }
                }
            }
        }
        
        // Extract creation/modification dates
        $tags['creation_date'] = $exiftool['QuickTime']['CreateDate']
            ?? $exiftool['QuickTime']['MediaCreateDate']
            ?? $formatTags['creation_time']
            ?? null;
            
        $tags['modification_date'] = $exiftool['QuickTime']['ModifyDate']
            ?? $formatTags['modification_time']
            ?? null;
        
        // Extract location if available
        if (isset($exiftool['Composite']['GPSPosition'])) {
            $tags['gps_position'] = $exiftool['Composite']['GPSPosition'];
        }
        if (isset($exiftool['QuickTime']['GPSCoordinates'])) {
            $tags['gps_coordinates'] = $exiftool['QuickTime']['GPSCoordinates'];
        }
        
        // Extract device info
        $tags['make'] = $exiftool['QuickTime']['Make'] ?? $exiftool['EXIF']['Make'] ?? null;
        $tags['model'] = $exiftool['QuickTime']['Model'] ?? $exiftool['EXIF']['Model'] ?? null;
        $tags['software'] = $exiftool['QuickTime']['Software'] ?? $exiftool['XMP']['CreatorTool'] ?? null;
        
        // Filter out null values
        return array_filter($tags, fn ($v) => $v !== null && $v !== '');
    }
    
    /**
     * Extract WAV-specific metadata
     */
    private function extractWavMetadata(array $rawMetadata): array
    {
        $exiftool = $rawMetadata['exiftool'] ?? [];
        
        $wav = [
            'encoding' => $exiftool['RIFF']['Encoding'] ?? null,
            'num_channels' => $exiftool['RIFF']['NumChannels'] ?? null,
            'sample_rate' => $exiftool['RIFF']['SampleRate'] ?? null,
            'avg_bytes_per_sec' => $exiftool['RIFF']['AvgBytesPerSec'] ?? null,
            'bits_per_sample' => $exiftool['RIFF']['BitsPerSample'] ?? null,
            'num_sample_frames' => $exiftool['RIFF']['NumSampleFrames'] ?? null,
        ];
        
        // BWF (Broadcast WAV) metadata
        if (isset($exiftool['RIFF']['Description'])) {
            $wav['bwf'] = [
                'description' => $exiftool['RIFF']['Description'] ?? null,
                'originator' => $exiftool['RIFF']['Originator'] ?? null,
                'originator_reference' => $exiftool['RIFF']['OriginatorReference'] ?? null,
                'origination_date' => $exiftool['RIFF']['OriginationDate'] ?? null,
                'origination_time' => $exiftool['RIFF']['OriginationTime'] ?? null,
                'time_reference' => $exiftool['RIFF']['TimeReference'] ?? null,
                'coding_history' => $exiftool['RIFF']['CodingHistory'] ?? null,
            ];
        }
        
        // iXML metadata (common in professional audio)
        if (isset($exiftool['XML'])) {
            $wav['ixml'] = $exiftool['XML'];
        }
        
        return array_filter($wav, fn ($v) => $v !== null);
    }
    
    /**
     * Extract QuickTime/MOV-specific metadata
     */
    private function extractQuicktimeMetadata(array $exiftool, array $mediainfo): array
    {
        $qt = $exiftool['QuickTime'] ?? [];
        
        $quicktime = [
            'major_brand' => $qt['MajorBrand'] ?? null,
            'minor_version' => $qt['MinorVersion'] ?? null,
            'compatible_brands' => $qt['CompatibleBrands'] ?? null,
            'movie_header_version' => $qt['MovieHeaderVersion'] ?? null,
            'time_scale' => $qt['TimeScale'] ?? null,
            'preferred_rate' => $qt['PreferredRate'] ?? null,
            'preferred_volume' => $qt['PreferredVolume'] ?? null,
            'poster_time' => $qt['PosterTime'] ?? null,
            'selection_duration' => $qt['SelectionDuration'] ?? null,
            'current_time' => $qt['CurrentTime'] ?? null,
            'handler_type' => $qt['HandlerType'] ?? null,
            'handler_description' => $qt['HandlerDescription'] ?? null,
            'compressor_name' => $qt['CompressorName'] ?? null,
            'graphics_mode' => $qt['GraphicsMode'] ?? null,
            'media_language_code' => $qt['MediaLanguageCode'] ?? null,
        ];
        
        // Recording device info
        if (isset($qt['Make']) || isset($qt['Model'])) {
            $quicktime['device'] = [
                'make' => $qt['Make'] ?? null,
                'model' => $qt['Model'] ?? null,
                'software' => $qt['Software'] ?? null,
            ];
        }
        
        // Location
        if (isset($qt['GPSCoordinates'])) {
            $quicktime['location'] = [
                'coordinates' => $qt['GPSCoordinates'],
                'altitude' => $qt['GPSAltitude'] ?? null,
            ];
        }
        
        return array_filter($quicktime, fn ($v) => $v !== null);
    }
    
    // ========================================================================
    // Waveform Generation
    // ========================================================================
    
    /**
     * Generate waveform image for audio file
     */
    public function generateWaveform(string $filePath, int $digitalObjectId): ?array
    {
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            return null;
        }
        
        $waveformDir = $this->uploadsDir . '/waveforms';
        if (!is_dir($waveformDir)) {
            mkdir($waveformDir, 0755, true);
        }
        
        $waveformFile = $waveformDir . '/waveform_' . $digitalObjectId . '.png';
        $width = $this->config['waveform_width'];
        $height = $this->config['waveform_height'];
        
        // Generate waveform using ffmpeg
        $cmd = sprintf(
            '%s -i %s -filter_complex "showwavespic=s=%dx%d:colors=#2980b9|#3498db:split_channels=1" -frames:v 1 -y %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($filePath),
            $width,
            $height,
            escapeshellarg($waveformFile)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($waveformFile)) {
            $this->logger->warning('Failed to generate waveform', ['output' => implode("\n", $output)]);
            return null;
        }
        
        return [
            'path' => '/uploads/waveforms/waveform_' . $digitalObjectId . '.png',
            'width' => $width,
            'height' => $height,
        ];
    }
    
    // ========================================================================
    // Database Storage
    // ========================================================================
    
    /**
     * Store extracted metadata in database
     */
    public function storeMetadata(int $digitalObjectId, ?int $objectId, array $metadata): int
    {
        $consolidated = $metadata['consolidated'] ?? [];
        
        // Delete existing
        DB::table('media_metadata')->where('digital_object_id', $digitalObjectId)->delete();
        
        $id = DB::table('media_metadata')->insertGetId([
            'digital_object_id' => $digitalObjectId,
            'object_id' => $objectId,
            'media_type' => $metadata['media_type'],
            'format' => $metadata['format'],
            'file_size' => $metadata['file_size'],
            
            // Duration and bitrate
            'duration' => $consolidated['duration'] ?? null,
            'bitrate' => $consolidated['bitrate'] ?? null,
            
            // Audio properties
            'audio_codec' => $consolidated['audio']['codec'] ?? null,
            'audio_sample_rate' => $consolidated['audio']['sample_rate'] ?? null,
            'audio_channels' => $consolidated['audio']['channels'] ?? null,
            'audio_bits_per_sample' => $consolidated['audio']['bits_per_sample'] ?? null,
            
            // Video properties
            'video_codec' => $consolidated['video']['codec'] ?? null,
            'video_width' => $consolidated['video']['width'] ?? null,
            'video_height' => $consolidated['video']['height'] ?? null,
            'video_frame_rate' => $consolidated['video']['frame_rate'] ?? null,
            
            // Tags
            'title' => $consolidated['tags']['title'] ?? null,
            'artist' => $consolidated['tags']['artist'] ?? null,
            'album' => $consolidated['tags']['album'] ?? null,
            'genre' => $consolidated['tags']['genre'] ?? null,
            'year' => $consolidated['tags']['year'] ?? null,
            'copyright' => $consolidated['tags']['copyright'] ?? null,
            
            // Full metadata JSON
            'raw_metadata' => json_encode($metadata),
            'consolidated_metadata' => json_encode($consolidated),
            
            // Waveform
            'waveform_path' => $metadata['waveform']['path'] ?? null,
            
            'extracted_at' => date('Y-m-d H:i:s'),
        ]);
        
        // Store chapters if present
        if (!empty($consolidated['chapters'])) {
            foreach ($consolidated['chapters'] as $index => $chapter) {
                DB::table('media_chapters')->insert([
                    'media_metadata_id' => $id,
                    'chapter_index' => $index,
                    'start_time' => $chapter['start'],
                    'end_time' => $chapter['end'],
                    'title' => $chapter['title'],
                ]);
            }
        }
        
        return $id;
    }
    
    /**
     * Get stored metadata for a digital object
     */
    public function getStoredMetadata(int $digitalObjectId): ?object
    {
        return DB::table('media_metadata')
            ->where('digital_object_id', $digitalObjectId)
            ->first();
    }
    
    /**
     * Get chapters for a digital object
     */
    public function getChapters(int $digitalObjectId): array
    {
        $metadata = $this->getStoredMetadata($digitalObjectId);
        
        if (!$metadata) {
            return [];
        }
        
        return DB::table('media_chapters')
            ->where('media_metadata_id', $metadata->id)
            ->orderBy('chapter_index')
            ->get()
            ->all();
    }
    
    // ========================================================================
    // Helper Methods
    // ========================================================================
    
    /**
     * Get file path for digital object - no hardcoded paths
     */
    private function getFilePath(object $do): string
    {
        $path = trim($do->path ?? '', '/');
        
        // Handle path that already includes /uploads/ prefix
        if (strpos($path, 'uploads/') === 0) {
            $path = substr($path, 8); // Remove 'uploads/' prefix
        }
        
        // Build full path using configured upload directory
        $fullPath = $this->uploadsDir . '/' . ltrim($path, '/');
        
        // Append filename if path doesn't already include it
        if (!empty($do->name) && substr($fullPath, -strlen($do->name)) !== $do->name) {
            $fullPath = rtrim($fullPath, '/') . '/' . $do->name;
        }
        
        return $fullPath;
    }
    
    private function formatDuration(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor((intval($seconds) % 3600) / 60);
        $secs = floor(intval($seconds) % 60);
        $ms = round(($seconds - floor($seconds)) * 1000);
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $ms);
        }
        return sprintf('%02d:%02d.%03d', $minutes, $secs, $ms);
    }
    
    private function formatBitrate(int $bitrate): string
    {
        if ($bitrate >= 1000000) {
            return round($bitrate / 1000000, 2) . ' Mbps';
        }
        return round($bitrate / 1000, 0) . ' kbps';
    }
    
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    private function parseFrameRate(string $frameRate): float
    {
        if (strpos($frameRate, '/') !== false) {
            list($num, $den) = explode('/', $frameRate);
            return $den > 0 ? round((float) $num / (float) $den, 3) : 0;
        }
        return (float) $frameRate;
    }
    
    /**
     * Check if file is a media file
     */
    public function isMediaFile(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, self::AUDIO_FORMATS) || in_array($ext, self::VIDEO_FORMATS);
    }
    
    /**
     * Get media type from extension
     */
    public function getMediaType(string $filename): ?string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, self::AUDIO_FORMATS)) {
            return 'audio';
        }
        if (in_array($ext, self::VIDEO_FORMATS)) {
            return 'video';
        }
        return null;
    }
    
    /**
     * Delete metadata for a digital object
     */
    public function deleteMetadata(int $digitalObjectId): bool
    {
        $metadata = $this->getStoredMetadata($digitalObjectId);
        
        if ($metadata) {
            // Delete chapters
            DB::table('media_chapters')
                ->where('media_metadata_id', $metadata->id)
                ->delete();
            
            // Delete waveform file if exists
            if ($metadata->waveform_path) {
                $waveformFile = $this->uploadsDir . $metadata->waveform_path;
                if (file_exists($waveformFile)) {
                    @unlink($waveformFile);
                }
            }
            
            // Delete metadata record
            DB::table('media_metadata')
                ->where('id', $metadata->id)
                ->delete();
            
            return true;
        }
        
        return false;
    }
}