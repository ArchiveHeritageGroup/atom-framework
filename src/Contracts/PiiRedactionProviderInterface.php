<?php

declare(strict_types=1);

namespace AtomFramework\Contracts;

/**
 * Interface for PII redaction providers.
 *
 * Plugins that provide PDF/document redaction capabilities should implement this.
 * Register via: AtomFramework\Providers::register('pii_redaction', $implementation)
 */
interface PiiRedactionProviderInterface
{
    /**
     * Get a redacted version of a PDF file.
     *
     * @param int $objectId The information object ID
     * @param string $originalPath Path to the original PDF
     * @return array ['success' => bool, 'path' => string, 'error' => ?string]
     */
    public function getRedactedPdf(int $objectId, string $originalPath): array;

    /**
     * Check if redaction is available for an object.
     *
     * @param int $objectId
     * @return bool
     */
    public function hasRedaction(int $objectId): bool;
}
