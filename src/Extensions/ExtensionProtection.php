<?php

namespace AtomFramework\Extensions;

use Illuminate\Database\Capsule\Manager as DB;

class ExtensionProtection
{
    const LEVELS = [
        'core' => [
            'can_disable' => false,
            'can_uninstall' => false,
            'label' => 'Core',
            'icon' => '🔒',
            'description' => 'Core AtoM functionality - cannot be modified'
        ],
        'system' => [
            'can_disable' => true,
            'can_uninstall' => false,
            'label' => 'System',
            'icon' => '⚙️',
            'description' => 'System plugin - can disable but not uninstall'
        ],
        'theme' => [
            'can_disable' => true,
            'can_uninstall' => true,
            'label' => 'Theme',
            'icon' => '🎨',
            'description' => 'Theme - can uninstall if not active'
        ],
        'extension' => [
            'can_disable' => true,
            'can_uninstall' => true,
            'label' => 'Extension',
            'icon' => '📦',
            'description' => 'Extension - full control'
        ]
    ];

    public static function getLevel(string $machineName): string
    {
        $extension = DB::table('atom_extension')
            ->where('machine_name', $machineName)
            ->first();
        
        return $extension->protection_level ?? 'extension';
    }

    public static function canDisable(string $machineName): array
    {
        $level = self::getLevel($machineName);
        $rules = self::LEVELS[$level] ?? self::LEVELS['extension'];
        
        if (!$rules['can_disable']) {
            return [
                'allowed' => false,
                'reason' => "Cannot disable {$rules['label']} plugin '{$machineName}'. {$rules['description']}."
            ];
        }
        
        return ['allowed' => true, 'reason' => null];
    }

    public static function canUninstall(string $machineName): array
    {
        $level = self::getLevel($machineName);
        $rules = self::LEVELS[$level] ?? self::LEVELS['extension'];
        
        if (!$rules['can_uninstall']) {
            return [
                'allowed' => false,
                'reason' => "Cannot uninstall {$rules['label']} plugin '{$machineName}'. {$rules['description']}."
            ];
        }
        
        // Check if theme is currently active
        if ($level === 'theme' && self::isActiveTheme($machineName)) {
            return [
                'allowed' => false,
                'reason' => "Cannot uninstall active theme '{$machineName}'. Switch to another theme first."
            ];
        }
        
        return ['allowed' => true, 'reason' => null];
    }

    protected static function isActiveTheme(string $machineName): bool
    {
        $plugin = DB::table('atom_plugin')
            ->where('name', $machineName)
            ->where('is_enabled', 0)  // 0 = enabled for themes
            ->first();
        
        return $plugin !== null;
    }

    public static function getInfo(string $machineName): array
    {
        $level = self::getLevel($machineName);
        $rules = self::LEVELS[$level] ?? self::LEVELS['extension'];
        
        return [
            'level' => $level,
            'label' => $rules['label'],
            'icon' => $rules['icon'],
            'can_disable' => $rules['can_disable'],
            'can_uninstall' => $rules['can_uninstall'],
            'description' => $rules['description']
        ];
    }

    public static function getLevelIcon(string $level): string
    {
        return self::LEVELS[$level]['icon'] ?? '📦';
    }
}
