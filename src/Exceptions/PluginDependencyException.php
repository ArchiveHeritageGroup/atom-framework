<?php

declare(strict_types=1);

namespace AtomFramework\Exceptions;

class PluginDependencyException extends PluginException
{
    protected array $missingDependencies;

    public function __construct(string $message, array $missingDependencies = [], string $pluginName = '')
    {
        $this->missingDependencies = $missingDependencies;
        parent::__construct($message, $pluginName, 422);
    }

    public function getMissingDependencies(): array
    {
        return $this->missingDependencies;
    }
}
