<?php
/**
 * Migration: Copy watermark images to images/watermarks
 * Date: 2026-01-05
 */

return new class {
    public function up(): void
    {
        // Get paths relative to framework location
        $frameworkPath = dirname(dirname(__DIR__));
        $atomRoot = dirname($frameworkPath);
        $sourceDir = $frameworkPath . '/dist/images/watermarks';
        $targetDir = $atomRoot . '/images/watermarks';
        
        if (!is_dir($sourceDir)) {
            echo "  Source watermark images not found at: $sourceDir\n";
            return;
        }
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $files = glob($sourceDir . '/*.png');
        $copied = 0;
        
        foreach ($files as $file) {
            $basename = basename($file);
            $target = $targetDir . '/' . $basename;
            if (copy($file, $target)) {
                $copied++;
            }
        }
        
        // Set permissions
        if (function_exists('posix_getpwnam')) {
            $www = posix_getpwnam('www-data');
            if ($www) {
                @chown($targetDir, $www['uid']);
                @chgrp($targetDir, $www['gid']);
                foreach (glob($targetDir . '/*') as $f) {
                    @chown($f, $www['uid']);
                    @chgrp($f, $www['gid']);
                }
            }
        }
        
        echo "  Copied $copied watermark images to $targetDir\n";
    }
    
    public function down(): void
    {
        // Don't remove images on rollback
    }
};
