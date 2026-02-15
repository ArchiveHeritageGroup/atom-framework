<?php

/**
 * sfEvent stub for standalone Heratio mode.
 *
 * Provides the sfEvent class when Symfony is not installed. Required because
 * 73 plugin Configuration classes have method signatures referencing sfEvent
 * (e.g., `public function filterAutoloadConfig(sfEvent $event, array $config)`).
 * PHP will fatal on class load if sfEvent is undefined.
 *
 * API-compatible with vendor/symfony/lib/event_dispatcher/sfEvent.php.
 * In dual-stack mode (Symfony present), the real sfEvent is loaded by
 * sfCoreAutoload and this file is never included.
 */
class sfEvent implements ArrayAccess
{
    protected $value = null;
    protected $processed = false;
    protected $subject = null;
    protected $name = '';
    protected $parameters = null;

    /**
     * Constructs a new sfEvent.
     *
     * @param mixed  $subject    The subject
     * @param string $name       The event name
     * @param array  $parameters An array of parameters
     */
    public function __construct($subject, $name, $parameters = [])
    {
        $this->subject = $subject;
        $this->name = $name;
        $this->parameters = $parameters;
    }

    /**
     * Returns the subject.
     *
     * @return mixed The subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Returns the event name.
     *
     * @return string The event name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the return value for this event.
     *
     * @param mixed $value The return value
     */
    public function setReturnValue($value)
    {
        $this->value = $value;
    }

    /**
     * Returns the return value.
     *
     * @return mixed The return value
     */
    public function getReturnValue()
    {
        return $this->value;
    }

    /**
     * Sets the processed flag.
     *
     * @param bool $processed The processed flag value
     */
    public function setProcessed($processed)
    {
        $this->processed = (bool) $processed;
    }

    /**
     * Returns whether the event has been processed by a listener or not.
     *
     * @return bool true if the event has been processed, false otherwise
     */
    public function isProcessed()
    {
        return $this->processed;
    }

    /**
     * Returns the event parameters.
     *
     * @return array The event parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Returns a parameter value via property access.
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value
     */
    public function __get($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new \InvalidArgumentException(sprintf('The event "%s" has no "%s" parameter.', $this->name, $name));
        }

        return $this->parameters[$name];
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($name)
    {
        return array_key_exists($name, $this->parameters);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($name)
    {
        unset($this->parameters[$name]);
    }
}
