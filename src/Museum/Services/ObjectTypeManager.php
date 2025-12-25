<?php

namespace AtomFramework\Museum\Services;

use Psr\Log\LoggerInterface;

class ObjectTypeManager
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get all available work types.
     *
     * @return array
     */
    public function getWorkTypes(): array
    {
        return array_keys($this->config['work_types']);
    }

    /**
     * Get work type configuration.
     *
     * @param string $workType
     *
     * @return array|null
     */
    public function getWorkTypeConfig(string $workType): ?array
    {
        return $this->config['work_types'][$workType] ?? null;
    }

    /**
     * Get work type label.
     *
     * @param string $workType
     *
     * @return string
     */
    public function getWorkTypeLabel(string $workType): string
    {
        $config = $this->getWorkTypeConfig($workType);

        return $config['label'] ?? ucwords(str_replace('_', ' ', $workType));
    }

    /**
     * Get work type description.
     *
     * @param string $workType
     *
     * @return string
     */
    public function getWorkTypeDescription(string $workType): string
    {
        $config = $this->getWorkTypeConfig($workType);

        return $config['description'] ?? '';
    }

    /**
     * Check if work type exists.
     *
     * @param string $workType
     *
     * @return bool
     */
    public function isValidWorkType(string $workType): bool
    {
        return isset($this->config['work_types'][$workType]);
    }

    /**
     * Get fields for a work type.
     *
     * @param string $workType
     * @param bool   $requiredOnly
     *
     * @return array
     */
    public function getFields(string $workType, bool $requiredOnly = false): array
    {
        $config = $this->getWorkTypeConfig($workType);

        if (!$config) {
            return [];
        }

        if ($requiredOnly) {
            return $config['required_fields'];
        }

        return array_merge(
            $config['required_fields'],
            $config['optional_fields'] ?? []
        );
    }

    /**
     * Suggest work type based on provided data.
     *
     * @param array $data
     *
     * @return string|null
     */
    public function suggestWorkType(array $data): ?string
    {
        $this->logger->debug('Suggesting work type', ['data' => $data]);

        // Simple heuristic-based suggestion
        $keywords = [
            'visual_works' => ['painting', 'drawing', 'print', 'photograph', 'sculpture'],
            'built_works' => ['building', 'architecture', 'monument', 'structure', 'garden'],
            'movable_works' => ['vessel', 'tool', 'textile', 'furniture', 'specimen'],
        ];

        $title = strtolower($data['title'] ?? '');
        $objectType = strtolower($data['work_type'] ?? '');
        $searchText = $title.' '.$objectType;

        foreach ($keywords as $workType => $terms) {
            foreach ($terms as $term) {
                if (strpos($searchText, $term) !== false) {
                    $this->logger->info('Work type suggested', [
                        'suggested' => $workType,
                        'match_term' => $term,
                    ]);

                    return $workType;
                }
            }
        }

        // Default to visual_works if uncertain
        return 'visual_works';
    }
}
