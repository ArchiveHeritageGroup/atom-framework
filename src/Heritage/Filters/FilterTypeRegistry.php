<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Filters;

use AtomFramework\Heritage\Repositories\FilterRepository;
use Illuminate\Support\Collection;

/**
 * Filter Type Registry.
 *
 * Registry of available filter types and their configurations.
 * Provides type definitions for the filter system.
 */
class FilterTypeRegistry
{
    private static ?Collection $types = null;
    private static FilterRepository $repository;

    /**
     * Get all registered filter types.
     */
    public static function all(): Collection
    {
        if (self::$types === null) {
            self::loadTypes();
        }

        return self::$types;
    }

    /**
     * Get filter type by code.
     */
    public static function get(string $code): ?object
    {
        return self::all()->firstWhere('code', $code);
    }

    /**
     * Check if filter type exists.
     */
    public static function has(string $code): bool
    {
        return self::get($code) !== null;
    }

    /**
     * Get system filter types.
     */
    public static function systemTypes(): Collection
    {
        return self::all()->where('is_system', 1);
    }

    /**
     * Get custom filter types.
     */
    public static function customTypes(): Collection
    {
        return self::all()->where('is_system', 0);
    }

    /**
     * Get filter types by source type.
     */
    public static function bySourceType(string $sourceType): Collection
    {
        return self::all()->where('source_type', $sourceType);
    }

    /**
     * Register a custom filter type.
     */
    public static function register(array $data): int
    {
        self::initRepository();

        $data['is_system'] = 0; // Custom types are never system types

        $id = self::$repository->createFilterType($data);

        // Refresh cache
        self::$types = null;

        return $id;
    }

    /**
     * Unregister a custom filter type.
     */
    public static function unregister(string $code): bool
    {
        self::initRepository();

        $type = self::get($code);
        if (!$type || $type->is_system) {
            return false;
        }

        $result = self::$repository->deleteFilterType($type->id);

        // Refresh cache
        self::$types = null;

        return $result;
    }

    /**
     * Get type definitions with metadata.
     */
    public static function getTypeDefinitions(): array
    {
        return [
            'taxonomy' => [
                'label' => 'Taxonomy',
                'description' => 'Values from AtoM taxonomy terms',
                'requires_reference' => true,
                'reference_label' => 'Taxonomy code',
                'examples' => ['contentType', 'subject', 'language'],
            ],
            'authority' => [
                'label' => 'Authority Record',
                'description' => 'Values from authority records (actors, places)',
                'requires_reference' => true,
                'reference_label' => 'Authority type',
                'examples' => ['actor', 'place'],
            ],
            'field' => [
                'label' => 'Field',
                'description' => 'Values from specific database fields',
                'requires_reference' => true,
                'reference_label' => 'Field name',
                'examples' => ['date', 'repository'],
            ],
            'custom' => [
                'label' => 'Custom',
                'description' => 'Manually defined filter values',
                'requires_reference' => false,
                'reference_label' => null,
                'examples' => [],
            ],
        ];
    }

    /**
     * Validate filter type data.
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['code'])) {
            $errors['code'] = 'Code is required';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $data['code'])) {
            $errors['code'] = 'Code must be lowercase alphanumeric with underscores';
        } elseif (self::has($data['code'])) {
            $errors['code'] = 'Code already exists';
        }

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (empty($data['source_type'])) {
            $errors['source_type'] = 'Source type is required';
        } elseif (!in_array($data['source_type'], ['taxonomy', 'authority', 'field', 'custom'])) {
            $errors['source_type'] = 'Invalid source type';
        }

        $definitions = self::getTypeDefinitions();
        if (isset($data['source_type']) && isset($definitions[$data['source_type']])) {
            $def = $definitions[$data['source_type']];
            if ($def['requires_reference'] && empty($data['source_reference'])) {
                $errors['source_reference'] = 'Source reference is required for ' . $data['source_type'] . ' type';
            }
        }

        return $errors;
    }

    /**
     * Load types from database.
     */
    private static function loadTypes(): void
    {
        self::initRepository();
        self::$types = self::$repository->getAllFilterTypes();
    }

    /**
     * Initialize repository.
     */
    private static function initRepository(): void
    {
        if (!isset(self::$repository)) {
            self::$repository = new FilterRepository();
        }
    }

    /**
     * Clear cache.
     */
    public static function clearCache(): void
    {
        self::$types = null;
    }
}
