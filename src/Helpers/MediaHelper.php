<?php

declare(strict_types=1);

namespace AtomFramework\Helpers;

/**
 * Media Helper - Enhanced media player with transcription support
 *
 * Handles streaming for legacy formats that browsers cannot play natively
 */
class MediaHelper
{
    /**
     * MIME types that need FFmpeg streaming/transcoding
     */
    public static function getStreamingMimeTypes(): array
    {
        return [
            // Video formats needing transcoding
            'video/x-ms-asf'        => 'video/mp4',
            'video/x-msvideo'       => 'video/mp4',
            'video/quicktime'       => 'video/mp4',
            'video/x-ms-wmv'        => 'video/mp4',
            'video/x-flv'           => 'video/mp4',
            'video/x-matroska'      => 'video/mp4',
            'video/mp2t'            => 'video/mp4',
            'video/x-ms-wtv'        => 'video/mp4',
            'video/hevc'            => 'video/mp4',
            'video/3gpp'            => 'video/mp4',
            'video/3gpp2'           => 'video/mp4',
            'application/vnd.rn-realmedia' => 'video/mp4',
            'video/x-ms-vob'        => 'video/mp4',
            'application/mxf'       => 'video/mp4',
            'video/x-f4v'           => 'video/mp4',
            'video/mpeg'            => 'video/mp4',
            'video/x-m2ts'          => 'video/mp4',
            'video/ogg'             => 'video/mp4',
            // Audio formats needing transcoding
            'audio/aiff'            => 'audio/mpeg',
            'audio/x-aiff'          => 'audio/mpeg',
            'audio/basic'           => 'audio/mpeg',
            'audio/x-au'            => 'audio/mpeg',
            'audio/ac3'             => 'audio/mpeg',
            'audio/8svx'            => 'audio/mpeg',
            'audio/AMB'             => 'audio/mpeg',
            'audio/x-ms-wma'        => 'audio/mpeg',
            'audio/x-pn-realaudio'  => 'audio/mpeg',
            'audio/flac'            => 'audio/mpeg',
            'audio/x-flac'          => 'audio/mpeg',
        ];
    }

    /**
     * File extensions that need streaming
     */
    public static function getStreamingExtensions(): array
    {
        return [
            // Video
            'asf', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'ts', 'wtv',
            'hevc', '3gp', '3g2', 'rm', 'rmvb', 'vob', 'mxf',
            'f4v', 'm2ts', 'mts', 'ogv', 'mpeg', 'mpg',
            // Audio
            'aiff', 'aif', 'au', 'snd', 'ac3', '8svx', 'amb',
            'wma', 'ra', 'ram', 'flac',
        ];
    }

    /**
     * Check if MIME type needs streaming
     */
    public static function needsStreaming(string $mimeType): bool
    {
        return array_key_exists(strtolower($mimeType), self::getStreamingMimeTypes());
    }

    /**
     * Check if file extension needs streaming
     */
    public static function extensionNeedsStreaming(string $extension): bool
    {
        return in_array(strtolower($extension), self::getStreamingExtensions());
    }

    /**
     * Get the output MIME type for transcoding
     */
    public static function getOutputMimeType(string $inputMimeType): string
    {
        $types = self::getStreamingMimeTypes();
        return $types[strtolower($inputMimeType)] ?? $inputMimeType;
    }

    /**
     * Build streaming URL for a digital object
     */
    public static function buildStreamingUrl(int $digitalObjectId, string $baseUrl = ''): string
    {
        if (empty($baseUrl)) {
            $baseUrl = \sfContext::getInstance()->getRequest()->getUriPrefix();
        }
        return rtrim($baseUrl, '/') . '/media/stream/' . $digitalObjectId;
    }

    /**
     * Check if FFmpeg is available
     */
    public static function isFFmpegAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        @exec('which ffmpeg 2>/dev/null', $output, $returnVar);
        return $returnVar === 0 && !empty($output);
    }

    /**
     * Get media duration using FFprobe
     */
    public static function getMediaDuration(string $filePath): ?float
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $cmd = sprintf(
            'ffprobe -v quiet -show_entries format=duration -of csv="p=0" %s 2>/dev/null',
            escapeshellarg($filePath)
        );

        $output = @shell_exec($cmd);
        if ($output !== null && is_numeric(trim($output))) {
            return (float) trim($output);
        }

        return null;
    }

    /**
     * Format duration as HH:MM:SS
     */
    public static function formatDuration(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}
