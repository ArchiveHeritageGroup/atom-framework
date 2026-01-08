<?php

declare(strict_types=1);

namespace AtomFramework\Commands;

use AtomFramework\Extensions\ExtensionManager;
use AtomFramework\Extensions\ExtensionProtection;

/**
 * CLI command to disable an extension.
 */
class ExtensionDisableCommand
{
    private ExtensionManager $manager;
    private ExtensionProtection $protection;

    public function __construct()
    {
        $this->manager = new ExtensionManager();
        $this->protection = new ExtensionProtection();
    }

    /**
     * Execute the disable command.
     *
     * @param string $pluginName Plugin name to disable
     * @param bool $force Force disable even with existing records
     *
     * @return int Exit code (0 = success, 1 = error)
     */
    public function execute(string $pluginName, bool $force = false): int
    {
        echo "\n";
        echo "AHG Extension Manager - Disable Plugin\n";
        echo str_repeat('─', 50) . "\n\n";

        // First check if plugin exists
        if (!$this->manager->exists($pluginName)) {
            $this->error("Plugin '{$pluginName}' not found.");

            return 1;
        }

        // Check protection status
        $checkResult = $this->protection->canDisable($pluginName, $force);

        if (!$checkResult['can_disable']) {
            $this->error("Cannot disable {$pluginName}");
            echo "\n";
            $this->warning("Reason: {$checkResult['reason']}");

            if ($checkResult['record_count'] > 0) {
                echo "\n";
                echo "To disable this plugin, you must first:\n";
                echo "  1. Export or migrate the existing records\n";
                echo "  2. Delete the records from the database\n";
                echo "  3. Then retry the disable command\n";
                echo "\n";
                $this->info("Use --force to override (WARNING: may cause data integrity issues)");
            }

            return 1;
        }

        // Proceed with disable
        $result = $this->manager->disable($pluginName, $force);

        if ($result['success']) {
            $this->success($result['message']);

            if ($result['record_count'] > 0) {
                echo "\n";
                $this->warning(sprintf(
                    'Note: %s records still exist in the database.',
                    number_format($result['record_count'])
                ));
            }

            return 0;
        }

        $this->error($result['message']);

        return 1;
    }

    private function success(string $message): void
    {
        echo "\033[32m✓ {$message}\033[0m\n";
    }

    private function error(string $message): void
    {
        echo "\033[31m✗ ERROR: {$message}\033[0m\n";
    }

    private function warning(string $message): void
    {
        echo "\033[33m⚠ {$message}\033[0m\n";
    }

    private function info(string $message): void
    {
        echo "\033[36mℹ {$message}\033[0m\n";
    }
}
