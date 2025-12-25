<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\MetadataExtraction\Contracts;

/**
 * Metadata Extractor Interface.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
interface MetadataExtractorInterface
{
    /**
     * Extract metadata from file.
     */
    public function extract(string $filePath): array;

    /**
     * Check if file type is supported.
     */
    public function supports(string $mimeType): bool;

    /**
     * Get extractor name.
     */
    public function getName(): string;
}
