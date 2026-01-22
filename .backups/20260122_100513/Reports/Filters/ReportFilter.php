<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Filters;

/**
 * Report Filter - encapsulates report query parameters.
 * Replaces scattered form getValue() calls with clean object.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class ReportFilter
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Create from Symfony form.
     */
    public static function fromForm(\sfForm $form): self
    {
        $filters = [];

        foreach ($form->getValues() as $key => $value) {
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return new self($filters);
    }

    /**
     * Get a filter value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    /**
     * Check if filter exists.
     */
    public function has(string $key): bool
    {
        return isset($this->filters[$key]) && $this->filters[$key] !== null && $this->filters[$key] !== '';
    }

    /**
     * Get all filters.
     */
    public function all(): array
    {
        return $this->filters;
    }

    /**
     * Set a filter value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->filters[$key] = $value;
    }

    /**
     * Merge with defaults.
     */
    public function withDefaults(array $defaults): self
    {
        return new self(array_merge($defaults, $this->filters));
    }
}
