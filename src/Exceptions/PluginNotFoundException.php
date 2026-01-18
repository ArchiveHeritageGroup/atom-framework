<?php

declare(strict_types=1);

namespace AtomFramework\Exceptions;

class PluginNotFoundException extends PluginException
{
    public function __construct(string $message, string $pluginName = '')
    {
        parent::__construct($message, $pluginName, 404);
    }
}
