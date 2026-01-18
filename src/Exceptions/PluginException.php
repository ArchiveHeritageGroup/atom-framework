<?php

declare(strict_types=1);

namespace AtomFramework\Exceptions;

use Exception;

class PluginException extends Exception
{
    protected string $pluginName;

    public function __construct(string $message, string $pluginName = '', int $code = 0, ?Exception $previous = null)
    {
        $this->pluginName = $pluginName;
        parent::__construct($message, $code, $previous);
    }

    public function getPluginName(): string
    {
        return $this->pluginName;
    }
}
