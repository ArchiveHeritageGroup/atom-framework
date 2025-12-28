<?php

namespace AtomFramework\Extensions;

/**
 * Extension Manager with protection level enforcement
 * 
 * Wraps ExtensionManager methods with protection checks
 */
trait ExtensionManagerProtected
{
    /**
     * Disable with protection check
     */
    public function disableProtected(string $machineName): array
    {
        $check = ExtensionProtection::canDisable($machineName);
        
        if (!$check['allowed']) {
            return [
                'success' => false,
                'message' => $check['reason']
            ];
        }
        
        return $this->disable($machineName);
    }

    /**
     * Uninstall with protection check
     */
    public function uninstallProtected(string $machineName, bool $backup = true): array
    {
        $check = ExtensionProtection::canUninstall($machineName);
        
        if (!$check['allowed']) {
            return [
                'success' => false,
                'message' => $check['reason']
            ];
        }
        
        return $this->uninstall($machineName, $backup);
    }
}
