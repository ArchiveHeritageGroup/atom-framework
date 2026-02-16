<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomFramework\Helpers\PathResolver;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Service for generating thumbnails from 3D model files (GLB, GLTF, OBJ, etc.)
 */
class ThreeDThumbnailService
{
    private string $toolPath;
    private string $logPath;
    private array $supported3DExtensions = ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'dae'];
    private array $supported3DMimeTypes = [
        'model/obj', 'model/gltf-binary', 'model/gltf+json', 'model/stl',
        'application/x-tgif', 'model/vnd.usdz+zip', 'application/x-ply',
    ];

    public function __construct()
    {
        $this->toolPath = PathResolver::getFrameworkDir() . '/tools/3d-thumbnail';

        // Fall back to plugin path if framework tools dir doesn't have the scripts
        $pluginPath = PathResolver::getRootDir() . '/plugins/ahg3DModelPlugin/tools/3d-thumbnail';
        if (!file_exists($this->toolPath . '/generate-thumbnail.sh') && file_exists($pluginPath . '/generate-thumbnail.sh')) {
            $this->toolPath = $pluginPath;
        }

        $this->logPath = PathResolver::getLogDir() . '/3d-thumbnail.log';
    }

    public function is3DMimeType(string $mime): bool
    {
        return in_array($mime, $this->supported3DMimeTypes);
    }

    public function getSupportedMimeTypes(): array
    {
        return $this->supported3DMimeTypes;
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logPath, $line, FILE_APPEND);
    }

    public function is3DModel(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $this->supported3DExtensions);
    }

    public function generateThumbnail(
        string $inputPath,
        string $outputPath,
        int $width = 512,
        int $height = 512
    ): bool {
        if (!file_exists($inputPath)) {
            $this->log("Input file not found: {$inputPath}", 'ERROR');
            return false;
        }

        $script = $this->toolPath . '/generate-thumbnail.sh';
        if (!file_exists($script)) {
            $this->log("Thumbnail script not found: {$script}", 'ERROR');
            return false;
        }

        $cmd = sprintf(
            '%s %s %s %d %d 2>&1',
            escapeshellcmd($script),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath),
            $width,
            $height
        );

        $this->log("Executing: {$cmd}");
        $output = shell_exec($cmd);
        $this->log("Output: {$output}", 'DEBUG');

        if (file_exists($outputPath) && filesize($outputPath) > 1000) {
            $this->log("Thumbnail generated: {$outputPath}");
            return true;
        }

        $this->log("Thumbnail generation failed for: {$inputPath}", 'WARNING');
        return false;
    }

    public function createDerivatives(int $digitalObjectId): bool
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->first();

        if (!$digitalObject) {
            $this->log("Digital object not found: {$digitalObjectId}", 'ERROR');
            return false;
        }

        if (!$this->is3DModel($digitalObject->name)) {
            $this->log("Not a 3D model: {$digitalObject->name}", 'DEBUG');
            return false;
        }

        $uploadsPath = PathResolver::getRootDir();
        $masterPath = $uploadsPath . $digitalObject->path . $digitalObject->name;

        if (!file_exists($masterPath)) {
            $this->log("Master file not found: {$masterPath}", 'ERROR');
            return false;
        }

        $baseName = pathinfo($digitalObject->name, PATHINFO_FILENAME);
        $derivativePath = dirname($masterPath) . '/';

        $referenceName = $baseName . '_reference.png';
        $thumbnailName = $baseName . '_thumbnail.png';

        $referencePath = $derivativePath . $referenceName;
        $thumbnailPath = $derivativePath . $thumbnailName;

        // Generate reference image (larger)
        $refSuccess = $this->generateThumbnail($masterPath, $referencePath, 480, 480);

        // Generate thumbnail (smaller)
        $thumbSuccess = $this->generateThumbnail($masterPath, $thumbnailPath, 150, 150);

        if (!$refSuccess && !$thumbSuccess) {
            return false;
        }

        // Get usage term IDs
        $referenceUsageId = $this->getTermId('Reference');
        $thumbnailUsageId = $this->getTermId('Thumbnail');

        if (!$referenceUsageId) {
            $referenceUsageId = $this->getTermId('reference image');
        }
        if (!$thumbnailUsageId) {
            $thumbnailUsageId = $this->getTermId('thumbnail image');
        }

        $this->log("Reference usage ID: {$referenceUsageId}, Thumbnail usage ID: {$thumbnailUsageId}");

        // Store reference derivative
        if ($refSuccess && file_exists($referencePath) && $referenceUsageId) {
            $this->createDerivativeRecord(
                $digitalObjectId,
                $referenceName,
                $digitalObject->path,
                $referenceUsageId,
                'image/png',
                filesize($referencePath)
            );
        }

        // Store thumbnail derivative
        if ($thumbSuccess && file_exists($thumbnailPath) && $thumbnailUsageId) {
            $this->createDerivativeRecord(
                $digitalObjectId,
                $thumbnailName,
                $digitalObject->path,
                $thumbnailUsageId,
                'image/png',
                filesize($thumbnailPath)
            );
        }

        // Set file ownership
        @chown($referencePath, 'www-data');
        @chown($thumbnailPath, 'www-data');
        @chgrp($referencePath, 'www-data');
        @chgrp($thumbnailPath, 'www-data');

        $this->log("Derivatives created for digital object: {$digitalObjectId}");
        return true;
    }

    private function createDerivativeRecord(
        int $parentId,
        string $name,
        string $path,
        int $usageId,
        string $mimeType,
        int $byteSize
    ): int {
        // Check if derivative already exists
        $existing = DB::table('digital_object')
            ->where('parent_id', $parentId)
            ->where('usage_id', $usageId)
            ->first();

        if ($existing) {
            DB::table('digital_object')
                ->where('id', $existing->id)
                ->update([
                    'name' => $name,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'byte_size' => $byteSize,
                ]);
            $this->log("Updated existing derivative: {$existing->id}");
            return $existing->id;
        }

        // AtoM requires object table entry first (foreign key constraint)
        // First create object record
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Then create digital_object with matching ID
        DB::table('digital_object')->insert([
            'id' => $objectId,
            'parent_id' => $parentId,
            'usage_id' => $usageId,
            'name' => $name,
            'path' => $path,
            'mime_type' => $mimeType,
            'byte_size' => $byteSize,
            'sequence' => 0,
        ]);
        
        $this->log("Created derivative record: {$objectId}");
        return $objectId;
    }

    private function getTermId(string $name): int
    {
        $term = DB::table('term_i18n')
            ->where('name', $name)
            ->first();

        return $term ? (int) $term->id : 0;
    }

    /**
     * Generate 6 multi-angle renders of a 3D model via Blender.
     *
     * @return array<string, string> Map of view name => file path (e.g. ['front' => '/path/front.png', ...])
     */
    public function generateMultiAngle(string $inputPath, string $outputDir, int $size = 1024): array
    {
        $views = ['front', 'back', 'left', 'right', 'top', 'detail'];

        if (!file_exists($inputPath)) {
            $this->log("Multi-angle input not found: {$inputPath}", 'ERROR');
            return [];
        }

        // Check cache: if all 6 PNGs exist and input is older, return cached
        $allExist = true;
        $results = [];
        foreach ($views as $view) {
            $png = rtrim($outputDir, '/') . '/' . $view . '.png';
            if (!file_exists($png)) {
                $allExist = false;
                break;
            }
            $results[$view] = $png;
        }

        if ($allExist && filemtime($inputPath) < filemtime($results['front'])) {
            $this->log("Multi-angle cache hit for: {$inputPath}");
            return $results;
        }

        // Create output dir
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $script = $this->toolPath . '/generate-multiangle.sh';
        if (!file_exists($script)) {
            $this->log("Multi-angle script not found: {$script}", 'ERROR');
            return [];
        }

        $cmd = sprintf(
            '%s %s %s %d 2>&1',
            escapeshellcmd($script),
            escapeshellarg($inputPath),
            escapeshellarg($outputDir),
            $size
        );

        $this->log("Multi-angle: {$cmd}");
        $output = shell_exec($cmd);
        $this->log("Multi-angle output: {$output}", 'DEBUG');

        $results = [];
        foreach ($views as $view) {
            $png = rtrim($outputDir, '/') . '/' . $view . '.png';
            if (file_exists($png) && filesize($png) > 500) {
                $results[$view] = $png;
                @chown($png, 'www-data');
                @chgrp($png, 'www-data');
            }
        }

        if (count($results) > 0) {
            $this->log("Multi-angle generated " . count($results) . " views for: {$inputPath}");
        } else {
            $this->log("Multi-angle generation failed for: {$inputPath}", 'WARNING');
        }

        return $results;
    }

    /**
     * Get the multi-angle output directory for a digital object.
     */
    public function getMultiAngleDir(int $digitalObjectId): string
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->first();

        if (!$digitalObject) {
            return '';
        }

        $uploadsPath = PathResolver::getRootDir();
        $masterDir = dirname($uploadsPath . $digitalObject->path . $digitalObject->name);

        return $masterDir . '/multiangle';
    }

    public function batchProcessExisting(): array
    {
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

        // Find all 3D digital objects without derivatives
        $objects = DB::table('digital_object as do')
            ->leftJoin('digital_object as deriv', 'deriv.parent_id', '=', 'do.id')
            ->whereNull('do.parent_id')
            ->whereNull('deriv.id')
            ->where(function ($query) {
                foreach ($this->supported3DExtensions as $ext) {
                    $query->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
            })
            ->select('do.id', 'do.name', 'do.path')
            ->get();

        $this->log("Found " . count($objects) . " 3D objects without thumbnails");
        echo "Found " . count($objects) . " 3D objects to process\n";

        foreach ($objects as $obj) {
            $results['processed']++;
            echo "Processing [{$results['processed']}]: {$obj->name}... ";
            
            try {
                if ($this->createDerivatives($obj->id)) {
                    $results['success']++;
                    echo "OK\n";
                } else {
                    $results['failed']++;
                    echo "FAILED\n";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $this->log("Exception: " . $e->getMessage(), 'ERROR');
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }

        return $results;
    }
}
