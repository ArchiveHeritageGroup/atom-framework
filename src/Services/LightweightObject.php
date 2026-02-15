<?php

declare(strict_types=1);

namespace AtomFramework\Services;

/**
 * Generic lightweight object wrapper for template compatibility.
 *
 * Wraps any stdClass and provides __get/__call/__isset so that
 * sfOutputEscaperObjectDecorator can call Propel-style getters
 * like getName(), getId(), etc.
 *
 * Used for: terms, level of description, taxonomies, and any
 * DB row that templates access via method calls.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class LightweightObject
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

    /**
     * Handle Propel-style getter calls (getName, getId, etc.)
     */
    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));

            if (isset($this->data->{$property})) {
                return $this->data->{$property};
            }

            // snake_case fallback
            $snakeCase = strtolower(preg_replace('/[A-Z]/', '_$0', $property));
            $snakeCase = ltrim($snakeCase, '_');
            if (isset($this->data->{$snakeCase})) {
                return $this->data->{$snakeCase};
            }

            return null;
        }

        return null;
    }

    public function __toString(): string
    {
        return $this->data->name ?? $this->data->title ?? '';
    }

    /**
     * Get raw data object.
     */
    public function getRawData(): object
    {
        return $this->data;
    }
}
