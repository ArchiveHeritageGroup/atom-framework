<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

/**
 * Wrapper for setting objects to provide QubitSetting-compatible interface
 */
class SettingWrapper implements \ArrayAccess
{
    private object $setting;

    public function __construct(object $setting)
    {
        $this->setting = $setting;
    }

    public function getValue(array $options = []): ?string
    {
        return $this->setting->value ?? $this->setting->_value ?? null;
    }

    public function getName(): ?string
    {
        return $this->setting->name ?? null;
    }

    public function getScope(): ?string
    {
        return $this->setting->scope ?? null;
    }

    public function getId(): ?int
    {
        return $this->setting->id ?? null;
    }

    public function getSourceCulture(): ?string
    {
        return $this->setting->source_culture ?? $this->setting->sourceCulture ?? null;
    }

    public function isEditable(): bool
    {
        return (bool) ($this->setting->editable ?? true);
    }

    public function isDeleteable(): bool
    {
        return (bool) ($this->setting->deleteable ?? true);
    }

    public function getSlug(): ?string
    {
        return $this->setting->name ?? null;
    }

    public function __get(string $name)
    {
        return $this->setting->$name ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->setting->$name);
    }

    public function __call(string $method, array $args)
    {
        // Handle get* methods
        if (str_starts_with($method, 'get')) {
            $property = lcfirst(substr($method, 3));
            $snakeProperty = strtolower(preg_replace('/([A-Z])/', '_$1', $property));
            
            return $this->setting->$property 
                ?? $this->setting->$snakeProperty 
                ?? null;
        }

        return null;
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return isset($this->setting->$offset);
    }

    public function offsetGet($offset): mixed
    {
        if ($offset === 'slug') {
            return $this->setting->name ?? null;
        }
        if ($offset === 'id') {
            return $this->setting->id ?? null;
        }
        return $this->setting->$offset ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->setting->$offset = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->setting->$offset);
    }
}
