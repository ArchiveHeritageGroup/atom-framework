<?php

namespace AtomFramework\Museum\Services;

use AtomFramework\Museum\Contracts\CcoValidatorInterface;
use Psr\Log\LoggerInterface;

class CcoValidator implements CcoValidatorInterface
{
    private array $config;
    private LoggerInterface $logger;
    private array $errors = [];

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function validate(array $data, string $workType): array
    {
        $this->errors = [];

        if (!isset($this->config['work_types'][$workType])) {
            $this->errors[] = "Invalid work type: {$workType}";
            $this->logger->error('CCO validation failed: invalid work type', [
                'work_type' => $workType,
                'data' => $data,
            ]);

            return ['valid' => false, 'errors' => $this->errors];
        }

        $workTypeConfig = $this->config['work_types'][$workType];
        $requiredFields = $workTypeConfig['required_fields'];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->errors[] = "Required field missing: {$field}";
            }
        }

        // Validate specific field types
        if (isset($data['measurements']) && !empty($data['measurements'])) {
            $this->validateMeasurements($data['measurements']);
        }

        if (isset($data['creation_date_earliest']) && isset($data['creation_date_latest'])) {
            $this->validateDateRange(
                $data['creation_date_earliest'],
                $data['creation_date_latest']
            );
        }

        $isValid = empty($this->errors);

        if (!$isValid) {
            $this->logger->warning('CCO validation failed', [
                'work_type' => $workType,
                'errors' => $this->errors,
                'data' => $data,
            ]);
        } else {
            $this->logger->info('CCO validation passed', [
                'work_type' => $workType,
            ]);
        }

        return ['valid' => $isValid, 'errors' => $this->errors];
    }

    public function getRequiredFields(string $workType): array
    {
        if (!isset($this->config['work_types'][$workType])) {
            return [];
        }

        return $this->config['work_types'][$workType]['required_fields'];
    }

    public function validateField(string $field, $value, string $workType): bool
    {
        if (empty($value)) {
            $requiredFields = $this->getRequiredFields($workType);
            if (in_array($field, $requiredFields)) {
                return false;
            }
        }

        // Field-specific validation
        switch ($field) {
            case 'measurements':
                if (!is_array($value)) {
                    return false;
                }
                $result = $this->validateMeasurements($value);

                return empty($this->errors);

            case 'creation_date_earliest':
            case 'creation_date_latest':
                return $this->isValidDate($value);

            case 'materials':
            case 'techniques':
                return is_array($value) && !empty($value);

            default:
                return true;
        }
    }

    private function validateMeasurements(array $measurements): void
    {
        $allowedTypes = $this->config['measurements']['allowed_types'] ?? [];
        $allowedUnits = array_merge(
            $this->config['measurements']['units']['length'] ?? [],
            $this->config['measurements']['units']['weight'] ?? []
        );

        foreach ($measurements as $measurement) {
            if (!is_array($measurement)) {
                $this->errors[] = 'Measurement must be an array';
                continue;
            }

            if (!isset($measurement['type']) || !in_array($measurement['type'], $allowedTypes)) {
                $this->errors[] = "Invalid measurement type: {$measurement['type']}";
            }

            if (!isset($measurement['value']) || !is_numeric($measurement['value'])) {
                $this->errors[] = 'Measurement value must be numeric';
            }

            if (!isset($measurement['unit']) || !in_array($measurement['unit'], $allowedUnits)) {
                $this->errors[] = "Invalid measurement unit: {$measurement['unit']}";
            }
        }
    }

    private function validateDateRange(string $earliest, string $latest): void
    {
        if (!$this->isValidDate($earliest)) {
            $this->errors[] = "Invalid earliest date format: {$earliest}";

            return;
        }

        if (!$this->isValidDate($latest)) {
            $this->errors[] = "Invalid latest date format: {$latest}";

            return;
        }

        // Compare dates if both are valid
        $earliestTimestamp = strtotime($earliest);
        $latestTimestamp = strtotime($latest);

        if ($earliestTimestamp > $latestTimestamp) {
            $this->errors[] = 'Earliest date cannot be after latest date';
        }
    }

    private function isValidDate(string $date): bool
    {
        // Support various date formats
        $formats = [
            'Y-m-d',
            'Y-m',
            'Y',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }

        return false;
    }
}
