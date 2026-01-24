<?php
/**
 * 3D Auto Config Service
 * 
 * Automatically creates viewer configurations for 3D digital objects
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

class ThreeDAutoConfigService
{
    /**
     * 3D file extensions
     */
    protected static array $extensions = ['glb', 'gltf', 'obj', 'fbx', 'stl', 'ply', 'usdz'];
    
    /**
     * Format mapping
     */
    protected static array $formatMap = [
        'glb' => 'glb',
        'gltf' => 'gltf',
        'obj' => 'obj',
        'fbx' => 'fbx',
        'stl' => 'stl',
        'ply' => 'ply',
        'usdz' => 'usdz'
    ];

    /**
     * Check if a filename is a 3D file
     */
    public static function is3DFile(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, self::$extensions);
    }

    /**
     * Create config for a single digital object
     * 
     * @param int $digitalObjectId
     * @return bool True if config was created, false if already exists or not 3D
     */
    public static function createConfigForDigitalObject(int $digitalObjectId): bool
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        
        if (!$do) {
            return false;
        }
        
        return self::createConfig($do);
    }

    /**
     * Create config for a digital object by object_id (information_object)
     * 
     * @param int $objectId
     * @return bool
     */
    public static function createConfigForObject(int $objectId): bool
    {
        $do = DB::table('digital_object')->where('object_id', $objectId)->first();
        
        if (!$do) {
            return false;
        }
        
        return self::createConfig($do);
    }

    /**
     * Create config from digital object record
     * 
     * @param object $digitalObject
     * @return bool
     */
    public static function createConfig(object $digitalObject): bool
    {
        // Check if it's a 3D file
        if (!self::is3DFile($digitalObject->name)) {
            return false;
        }
        
        // Check if config already exists
        $existing = DB::table('object_3d_model')
            ->where('object_id', $digitalObject->object_id)
            ->first();
        
        if ($existing) {
            return false; // Already has config
        }
        
        // Determine format
        $ext = strtolower(pathinfo($digitalObject->name, PATHINFO_EXTENSION));
        $format = self::$formatMap[$ext] ?? 'glb';
        
        // Build file path
        $filePath = '/uploads/r/' . ($digitalObject->path ?? '') . '/' . $digitalObject->name;
        
        // Insert config with defaults
        DB::table('object_3d_model')->insert([
            'object_id' => $digitalObject->object_id,
            'filename' => $digitalObject->name,
            'original_filename' => $digitalObject->name,
            'file_path' => $filePath,
            'file_size' => $digitalObject->byte_size ?? 0,
            'mime_type' => $digitalObject->mime_type ?? 'model/gltf-binary',
            'format' => $format,
            'auto_rotate' => 1,
            'rotation_speed' => 1.00,
            'camera_orbit' => '0deg 75deg 105%',
            'field_of_view' => '30deg',
            'exposure' => 1.00,
            'shadow_intensity' => 1.00,
            'shadow_softness' => 1.00,
            'background_color' => '#f5f5f5',
            'ar_enabled' => 1,
            'ar_scale' => 'auto',
            'ar_placement' => 'floor',
            'is_primary' => 1,
            'is_public' => 1,
            'display_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        // Log the creation
        self::log("Auto-created 3D config for object_id: {$digitalObject->object_id}, file: {$digitalObject->name}");
        
        return true;
    }

    /**
     * Process all unconfigured 3D files
     * 
     * @return int Number of configs created
     */
    public static function processAllUnconfigured(): int
    {
        $unconfigured = DB::table('digital_object as d')
            ->leftJoin('object_3d_model as m', 'd.object_id', '=', 'm.object_id')
            ->whereNull('m.id')
            ->where(function($q) {
                foreach (self::$extensions as $ext) {
                    $q->orWhere('d.name', 'like', '%.' . $ext);
                }
                $q->orWhere('d.mime_type', 'like', '%gltf%');
            })
            ->select('d.*')
            ->get();
        
        $created = 0;
        foreach ($unconfigured as $do) {
            if (self::createConfig($do)) {
                $created++;
            }
        }
        
        return $created;
    }

    /**
     * Check recent uploads and create configs (for cron job)
     * 
     * @param int $minutes Check uploads from last N minutes
     * @return int Number of configs created
     */
    public static function processRecentUploads(int $minutes = 60): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        $recent = DB::table('digital_object as d')
            ->leftJoin('object_3d_model as m', 'd.object_id', '=', 'm.object_id')
            ->whereNull('m.id')
            ->where('d.created_at', '>=', $since)
            ->where(function($q) {
                foreach (self::$extensions as $ext) {
                    $q->orWhere('d.name', 'like', '%.' . $ext);
                }
                $q->orWhere('d.mime_type', 'like', '%gltf%');
            })
            ->select('d.*')
            ->get();
        
        $created = 0;
        foreach ($recent as $do) {
            if (self::createConfig($do)) {
                $created++;
            }
        }
        
        return $created;
    }

    /**
     * Log message
     */
    protected static function log(string $message): void
    {
        $logFile = sfConfig::get('sf_log_dir', '/tmp') . '/3d_auto_config.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}
