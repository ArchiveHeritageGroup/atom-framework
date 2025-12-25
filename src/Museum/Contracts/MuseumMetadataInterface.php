<?php

namespace AtomFramework\Museum\Contracts;

interface MuseumMetadataInterface
{
    /**
     * Get CCO fields for a specific work type.
     *
     * @param string $workType The CCO work type (visual_works, built_works, etc.)
     *
     * @return array Array of field definitions
     */
    public function getCcoFields(string $workType): array;

    /**
     * Validate data against CCO requirements for a work type.
     *
     * @param array  $data     The data to validate
     * @param string $workType The CCO work type
     *
     * @return bool True if validation passes
     */
    public function validateCcoRequirements(array $data, string $workType): bool;

    /**
     * Enrich object with CCO metadata.
     *
     * @param int   $objectId         The information object ID
     * @param array $museumProperties Museum-specific properties
     *
     * @return void
     */
    public function enrichWithCcoMetadata(int $objectId, array $museumProperties): void;

    /**
     * Get validation errors from last validation attempt.
     *
     * @return array Array of validation error messages
     */
    public function getValidationErrors(): array;
}
