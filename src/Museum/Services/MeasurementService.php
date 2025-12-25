<?php

namespace AtomFramework\Museum\Services;

use AtomFramework\Museum\Models\Measurement;
use Psr\Log\LoggerInterface;

class MeasurementService
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Create a measurement from array data.
     *
     * @param array $data
     *
     * @return Measurement
     */
    public function createMeasurement(array $data): Measurement
    {
        $type = $data['type'] ?? 'unknown';
        $value = (float) ($data['value'] ?? 0);
        $unit = $data['unit'] ?? 'cm';
        $part = $data['part'] ?? null;

        return new Measurement($type, $value, $unit, $part);
    }

    /**
     * Parse measurements from various formats.
     *
     * @param mixed $input
     *
     * @return array Array of Measurement objects
     */
    public function parseMeasurements($input): array
    {
        if (is_string($input)) {
            return $this->parseFromString($input);
        }

        if (is_array($input)) {
            return $this->parseFromArray($input);
        }

        $this->logger->warning('Invalid measurement input type', [
            'type' => gettype($input),
        ]);

        return [];
    }

    /**
     * Format measurements for display.
     *
     * @param array $measurements
     * @param bool  $grouped
     *
     * @return string
     */
    public function formatMeasurements(array $measurements, bool $grouped = true): string
    {
        if (empty($measurements)) {
            return '';
        }

        $formatted = [];

        foreach ($measurements as $measurement) {
            if ($measurement instanceof Measurement) {
                $formatted[] = $measurement->format();
            } elseif (is_array($measurement)) {
                $m = $this->createMeasurement($measurement);
                $formatted[] = $m->format();
            }
        }

        return implode('; ', $formatted);
    }

    /**
     * Convert all measurements to a target unit system.
     *
     * @param array  $measurements
     * @param string $unitSystem 'metric' or 'imperial'
     *
     * @return array
     */
    public function convertUnitSystem(array $measurements, string $unitSystem): array
    {
        $converted = [];
        $targetUnits = $this->getDefaultUnits($unitSystem);

        foreach ($measurements as $measurement) {
            if (!$measurement instanceof Measurement) {
                $measurement = $this->createMeasurement($measurement);
            }

            $type = $measurement->getType();
            $category = $this->getMeasurementCategory($type);
            $targetUnit = $targetUnits[$category] ?? $measurement->getUnit();

            try {
                $convertedValue = $measurement->convertTo($targetUnit);
                $converted[] = new Measurement(
                    $type,
                    round($convertedValue, $this->config['measurements']['precision']),
                    $targetUnit,
                    $measurement->getPart()
                );
            } catch (\InvalidArgumentException $e) {
                $this->logger->warning('Failed to convert measurement', [
                    'error' => $e->getMessage(),
                    'measurement' => $measurement->toArray(),
                ]);
                $converted[] = $measurement;
            }
        }

        return $converted;
    }

    /**
     * Get extent statement for measurements (overall dimensions).
     *
     * @param array $measurements
     *
     * @return string
     */
    public function getExtentStatement(array $measurements): string
    {
        $dimensions = ['height' => null, 'width' => null, 'depth' => null];
        $unit = null;

        foreach ($measurements as $measurement) {
            if (!$measurement instanceof Measurement) {
                $measurement = $this->createMeasurement($measurement);
            }

            $type = $measurement->getType();
            if (isset($dimensions[$type]) && $measurement->getPart() === null) {
                $dimensions[$type] = $measurement->getValue();
                $unit = $measurement->getUnit();
            }
        }

        $parts = [];
        if ($dimensions['height'] !== null) {
            $parts[] = "H: {$dimensions['height']}";
        }
        if ($dimensions['width'] !== null) {
            $parts[] = "W: {$dimensions['width']}";
        }
        if ($dimensions['depth'] !== null) {
            $parts[] = "D: {$dimensions['depth']}";
        }

        if (empty($parts)) {
            return '';
        }

        return implode(' × ', $parts).($unit ? " {$unit}" : '');
    }

    private function parseFromString(string $input): array
    {
        // Parse strings like "H: 50cm, W: 30cm, D: 10cm"
        $measurements = [];
        $parts = preg_split('/[;,]/', $input);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            // Match patterns like "H: 50cm" or "Height: 50 cm" or "50cm height"
            if (preg_match('/([a-z]+)[:=\s]+([0-9.]+)\s*([a-z]+)/i', $part, $matches)) {
                $type = $this->normalizeType($matches[1]);
                $value = (float) $matches[2];
                $unit = strtolower($matches[3]);

                $measurements[] = new Measurement($type, $value, $unit);
            }
        }

        return $measurements;
    }

    private function parseFromArray(array $input): array
    {
        $measurements = [];

        foreach ($input as $item) {
            if ($item instanceof Measurement) {
                $measurements[] = $item;
            } elseif (is_array($item)) {
                $measurements[] = $this->createMeasurement($item);
            }
        }

        return $measurements;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        $mapping = [
            'h' => 'height',
            'w' => 'width',
            'd' => 'depth',
            'dia' => 'diameter',
            'diam' => 'diameter',
            'l' => 'length',
            'wt' => 'weight',
        ];

        return $mapping[$type] ?? $type;
    }

    private function getMeasurementCategory(string $type): string
    {
        $weightTypes = ['weight'];
        if (in_array($type, $weightTypes)) {
            return 'weight';
        }

        return 'length';
    }

    private function getDefaultUnits(string $unitSystem): array
    {
        if ($unitSystem === 'imperial') {
            return [
                'length' => 'in',
                'weight' => 'lb',
            ];
        }

        // Default to metric
        return [
            'length' => 'cm',
            'weight' => 'kg',
        ];
    }
}
