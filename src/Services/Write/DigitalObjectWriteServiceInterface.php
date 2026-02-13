<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for digital object write operations.
 *
 * Covers: creating DOs with file upload, updating metadata,
 * managing properties, and creating derivative records.
 *
 * The PropelAdapter wraps QubitDigitalObject/QubitAsset for
 * Symfony mode. A future LaravelAdapter would use direct DB
 * inserts + file handling.
 */
interface DigitalObjectWriteServiceInterface
{
    /**
     * Create a new digital object attached to an information object.
     *
     * Handles file storage, asset creation, and usage-ID assignment.
     *
     * @param int         $objectId  Parent information_object.id
     * @param string      $filename  Original filename
     * @param string      $content   Raw file content (binary)
     * @param int|null    $usageId   Usage term ID (defaults to MASTER)
     *
     * @return int The new digital_object.id
     */
    public function create(int $objectId, string $filename, string $content, ?int $usageId = null): int;

    /**
     * Update digital object metadata (media type, name, etc.).
     *
     * @param int   $id         digital_object.id
     * @param array $attributes Column => value pairs to update
     */
    public function updateMetadata(int $id, array $attributes): void;

    /**
     * Save or update a property on a digital object.
     *
     * Properties are stored in object → property → property_i18n.
     *
     * @param int         $objectId   The object.id (digital object or parent)
     * @param string      $name       Property name
     * @param string|null $value      Property value (null to delete)
     * @param string      $culture    Culture code
     */
    public function saveProperty(int $objectId, string $name, ?string $value, string $culture = 'en'): void;

    /**
     * Create a derivative record (reference image, thumbnail, track).
     *
     * @param int   $parentId   Parent digital_object.id (master)
     * @param array $attributes Derivative attributes (usage_id, name, path, etc.)
     *
     * @return int The new derivative digital_object.id
     */
    public function createDerivative(int $parentId, array $attributes): int;

    /**
     * Delete a digital object and its derivatives.
     *
     * @param int $id digital_object.id
     *
     * @return bool True if deleted
     */
    public function delete(int $id): bool;

    /**
     * Update file metadata after processing (mime type, size, dimensions, etc.).
     *
     * @param int   $id       digital_object.id
     * @param array $metadata File metadata (byte_size, mime_type, etc.)
     */
    public function updateFileMetadata(int $id, array $metadata): void;
}
