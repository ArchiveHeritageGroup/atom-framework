<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\MetadataExtraction;

use AtomExtensions\Extensions\MetadataExtraction\Repositories\MetadataRepository;
use AtomExtensions\Extensions\MetadataExtraction\Services\Extractors\ExifToolExtractor;
use AtomExtensions\Extensions\MetadataExtraction\Services\MetadataExtractionService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Metadata Extraction Adapter.
 *
 * Provides backward compatibility with existing metadata extraction plugin.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class MetadataExtractionAdapter
{
    private static ?MetadataExtractionService $service = null;

    /**
     * Get service instance.
     */
    public static function getService(): MetadataExtractionService
    {
        if (self::$service === null) {
            $repository = new MetadataRepository();

            $logger = new Logger('metadata-extraction');
            $logger->pushHandler(
                new StreamHandler(
                    sfConfig::get('sf_log_dir', '/var/log/atom') . '/metadata-extraction.log',
                    Logger::INFO
                )
            );

            self::$service = new MetadataExtractionService($repository, $logger);

            // Register extractors
            self::$service->registerExtractor(new ExifToolExtractor());
        }

        return self::$service;
    }

    /**
     * Extract metadata from digital object.
     */
    public static function extract(int $digitalObjectId, bool $save = true): array
    {
        return self::getService()->extractFromDigitalObject($digitalObjectId, $save);
    }

    /**
     * Get metadata for digital object.
     */
    public static function getMetadata(int $digitalObjectId): array
    {
        return self::getService()->getMetadata($digitalObjectId);
    }

    /**
     * Delete metadata for digital object.
     */
    public static function deleteMetadata(int $digitalObjectId): void
    {
        self::getService()->deleteMetadata($digitalObjectId);
    }
}
