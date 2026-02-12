<?php

namespace AtomFramework\Http\Compatibility;

/**
 * Standalone sfOutputEscaper implementation for when Symfony is not loaded.
 *
 * Provides unescape() — the most commonly used method in AHG templates —
 * and a minimal sfOutputEscaperObjectDecorator that passes through to
 * the wrapped object.
 *
 * In Symfony mode, the real sfOutputEscaper is loaded first, so these
 * class_exists() guards prevent conflicts.
 */
class EscaperShim
{
    /**
     * Register the shim classes as global sfOutputEscaper / sfOutputEscaperObjectDecorator.
     *
     * Only call when Symfony is NOT loaded.
     */
    public static function register(): void
    {
        if (!class_exists('sfOutputEscaper', false)) {
            class_alias(self::class, 'sfOutputEscaper');
        }

        if (!class_exists('sfOutputEscaperObjectDecorator', false)) {
            class_alias(EscaperObjectDecoratorShim::class, 'sfOutputEscaperObjectDecorator');
        }

        if (!class_exists('sfOutputEscaperArrayDecorator', false)) {
            class_alias(EscaperArrayDecoratorShim::class, 'sfOutputEscaperArrayDecorator');
        }
    }

    /**
     * Unescape a value — returns the raw value.
     *
     * In Symfony mode, sfOutputEscaper::unescape() unwraps decorator objects.
     * In standalone mode, values are never escaped/wrapped, so we just pass through.
     *
     * @param mixed $value The value to unescape
     *
     * @return mixed The raw value
     */
    public static function unescape($value)
    {
        // If the value is a decorator shim, unwrap it
        if ($value instanceof EscaperObjectDecoratorShim) {
            return $value->getRawValue();
        }

        if ($value instanceof EscaperArrayDecoratorShim) {
            return $value->getRawValue();
        }

        // In standalone mode, values are never wrapped — pass through
        if (is_array($value)) {
            return array_map([self::class, 'unescape'], $value);
        }

        return $value;
    }

    /**
     * Escape a value — identity function in standalone mode.
     */
    public static function escape($escapingMethod, $value)
    {
        return $value;
    }
}

/**
 * Standalone sfOutputEscaperObjectDecorator — thin wrapper around an object.
 *
 * In Symfony mode, this wraps Propel objects for XSS-safe template output.
 * In standalone mode, we don't wrap objects, but this class exists to
 * prevent "class not found" errors in code that type-checks against it.
 */
class EscaperObjectDecoratorShim
{
    private $value;

    public function __construct($escapingMethod = null, $value = null)
    {
        $this->value = $value ?? $escapingMethod;
    }

    /**
     * Get the raw (unwrapped) value.
     */
    public function getRawValue()
    {
        return $this->value;
    }

    /**
     * Delegate property access to the wrapped object.
     */
    public function __get(string $name)
    {
        if (is_object($this->value)) {
            return $this->value->$name;
        }

        return null;
    }

    /**
     * Delegate isset to the wrapped object.
     */
    public function __isset(string $name): bool
    {
        if (is_object($this->value)) {
            return isset($this->value->$name);
        }

        return false;
    }

    /**
     * Delegate method calls to the wrapped object.
     */
    public function __call(string $method, array $args)
    {
        if (is_object($this->value) && method_exists($this->value, $method)) {
            return call_user_func_array([$this->value, $method], $args);
        }

        return null;
    }

    /**
     * Delegate toString to the wrapped object.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}

/**
 * Standalone sfOutputEscaperArrayDecorator.
 */
class EscaperArrayDecoratorShim implements \ArrayAccess, \Countable, \IteratorAggregate
{
    private $value;

    public function __construct($escapingMethod = null, $value = null)
    {
        $this->value = $value ?? $escapingMethod;
        if (!is_array($this->value)) {
            $this->value = [];
        }
    }

    public function getRawValue()
    {
        return $this->value;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->value[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->value[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->value[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->value[$offset]);
    }

    public function count(): int
    {
        return count($this->value);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->value);
    }
}
