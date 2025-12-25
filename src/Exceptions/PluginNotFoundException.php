<?php

declare(strict_types=1);

namespace Atom\Framework\Exceptions;

class PluginNotFoundException extends PluginException
{
    public function __construct(string $message, string $pluginName = '')
    {
        parent::__construct($message, $pluginName, 404);
    }
}
