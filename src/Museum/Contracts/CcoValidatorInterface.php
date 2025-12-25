<?php

namespace AtomFramework\Museum\Contracts;

interface CcoValidatorInterface
{
    /**
     * Validate museum object data against CCO requirements.
     *
     * @param array  $data     The data to validate
     * @param string $workType The CCO work type
     *
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validate(array $data, string $workType): array;

    /**
     * Get required fields for a specific work type.
     *
     * @param string $workType The CCO work type
     *
     * @return array Array of required field names
     */
    public function getRequiredFields(string $workType): array;

    /**
     * Check if a specific field is valid.
     *
     * @param string $field The field name
     * @param mixed  $value The field value
     * @param string $workType The CCO work type
     *
     * @return bool True if field is valid
     */
    public function validateField(string $field, $value, string $workType): bool;
}
