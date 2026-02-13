<?php

declare(strict_types=1);

namespace AtomFramework\Services;

/**
 * Lightweight resource wrapper for template compatibility.
 *
 * Templates access: $resource->title, $resource->slug, $resource->id
 * This wraps a stdClass from EntityQueryService and provides __get/__isset/__toString.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class LightweightResource
{
    private object $data;

    public function __construct(object $data)
    {
        $this->data = $data;
    }

    public function __get(string $name)
    {
        return $this->data->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data->{$name});
    }

    public function __toString(): string
    {
        return $this->data->title
            ?? $this->data->authorized_form_of_name
            ?? $this->data->name
            ?? $this->data->slug
            ?? '';
    }

    /**
     * Get the raw underlying stdClass data.
     */
    public function getRawData(): object
    {
        return $this->data;
    }

    /**
     * Convert to array for serialization or debugging.
     */
    public function toArray(): array
    {
        return (array) $this->data;
    }
}
