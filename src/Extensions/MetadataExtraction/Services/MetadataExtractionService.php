<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\MetadataExtraction\Services;

use AtomExtensions\Extensions\MetadataExtraction\Contracts\MetadataExtractorInterface;
use AtomExtensions\Extensions\MetadataExtraction\Repositories\MetadataRepository;
use Psr\Log\LoggerInterface;

/**
 * Metadata Extraction Service.
 *
 * Coordinates metadata extraction from digital objects.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class MetadataExtractionService
{
    /** @var MetadataExtractorInterface[] */
    private array $extractors = [];

    public function __construct(
        private readonly MetadataRepository $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Register an extractor.
     */
    public function registerExtractor(MetadataExtractorInterface $extractor): void
    {
        $this->extractors[] = $extractor;
    }

    /**
     * Extract metadata from digital object.
     */
    public function extractFromDigitalObject(int $digitalObjectId, bool $save = true): array
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            throw new \RuntimeException("Digital object {$digitalObjectId} not found");
        }

        $filePath = sfConfig::get('sf_web_dir') . '/' . $digitalObject->path;

        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        // Find suitable extractor
        $extractor = $this->findExtractor($digitalObject->mime_type);

        if (!$extractor) {
            $this->logger->warning("No extractor found for mime type: {$digitalObject->mime_type}");

            return [];
        }

        $this->logger->info("Extracting metadata from {$filePath} using {$extractor->getName()}");

        try {
            $metadata = $extractor->extract($filePath);

            if ($save && !empty($metadata)) {
                $this->saveMetadata($digitalObjectId, $metadata);
            }

            return $metadata;
        } catch (\Exception $e) {
            $this->logger->error("Metadata extraction failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Save extracted metadata.
     */
    private function saveMetadata(int $digitalObjectId, array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $this->repository->saveMetadata(
                $digitalObjectId,
                $key,
                (string) $value,
                'metadata_extraction'
            );
        }

        $this->logger->info('Saved ' . count($metadata) . " metadata fields for digital object {$digitalObjectId}");
    }

    /**
     * Find suitable extractor for mime type.
     */
    private function findExtractor(string $mimeType): ?MetadataExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mimeType)) {
                return $extractor;
            }
        }

        return null;
    }

    /**
     * Get metadata for digital object.
     */
    public function getMetadata(int $digitalObjectId): array
    {
        return $this->repository->getMetadata($digitalObjectId);
    }

    /**
     * Delete metadata for digital object.
     */
    public function deleteMetadata(int $digitalObjectId): void
    {
        $this->repository->deleteMetadata($digitalObjectId);
    }
}
