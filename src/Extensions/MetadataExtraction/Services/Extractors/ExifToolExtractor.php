<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\MetadataExtraction\Services\Extractors;

use AtomExtensions\Extensions\MetadataExtraction\Contracts\MetadataExtractorInterface;

/**
 * ExifTool Metadata Extractor.
 *
 * Uses exiftool to extract metadata from images and documents.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ExifToolExtractor implements MetadataExtractorInterface
{
    private string $exifToolPath;

    public function __construct(?string $exifToolPath = null)
    {
        $this->exifToolPath = $exifToolPath ?? '/usr/bin/exiftool';
    }

    /**
     * Extract metadata from file.
     */
    public function extract(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if (!file_exists($this->exifToolPath)) {
            throw new \RuntimeException("exiftool not found at: {$this->exifToolPath}");
        }

        // Execute exiftool with JSON output
        $command = sprintf(
            '%s -json -a -G1 %s 2>&1',
            escapeshellcmd($this->exifToolPath),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('exiftool failed: ' . implode("\n", $output));
        }

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse exiftool output: ' . json_last_error_msg());
        }

        // exiftool returns array with single element
        return $data[0] ?? [];
    }

    /**
     * Check if file type is supported.
     */
    public function supports(string $mimeType): bool
    {
        // exiftool supports many formats
        $supportedTypes = [
            'image/jpeg',
            'image/png',
            'image/tiff',
            'image/gif',
            'image/bmp',
            'image/webp',
            'application/pdf',
            'video/mp4',
            'video/avi',
            'audio/mpeg',
        ];

        return in_array($mimeType, $supportedTypes);
    }

    /**
     * Get extractor name.
     */
    public function getName(): string
    {
        return 'ExifTool';
    }
}
