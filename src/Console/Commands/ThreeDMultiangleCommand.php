<?php

namespace AtomFramework\Console\Commands;

use AtomExtensions\Services\ThreeDThumbnailService;
use AtomFramework\Console\BaseCommand;
use AtomFramework\Helpers\PathResolver;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Generate multi-angle renders of 3D models for AI description and gallery display.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ThreeDMultiangleCommand extends BaseCommand
{
    protected string $name = '3d:multiangle';
    protected string $description = 'Generate multi-angle renders of 3D models';
    protected string $detailedDescription = <<<'EOF'
Render 6 views (front, back, left, right, top, detail) of 3D models
using Blender. Optionally describe the object via LLM.

  <bold>php bin/atom 3d:multiangle</bold>                   Render all 3D objects missing multi-angle views
  <bold>php bin/atom 3d:multiangle --id=1406</bold>         Render specific digital object
  <bold>php bin/atom 3d:multiangle --force</bold>           Re-render even if views exist
  <bold>php bin/atom 3d:multiangle --describe</bold>        After rendering, output LLM description
  <bold>php bin/atom 3d:multiangle --dry-run</bold>         List objects without processing
EOF;

    protected function configure(): void
    {
        $this->addOption('id', null, 'Process a specific digital object ID');
        $this->addOption('force', 'f', 'Re-render even if multi-angle views exist', false);
        $this->addOption('describe', 'd', 'Output LLM description after rendering', false);
        $this->addOption('dry-run', null, 'List 3D objects without processing', false);
    }

    protected function handle(): int
    {
        $service = new ThreeDThumbnailService();
        $id = $this->option('id');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $describe = $this->option('describe');

        if ($id) {
            return $this->processSingle($service, (int) $id, $force, $dryRun, $describe);
        }

        return $this->processAll($service, $force, $dryRun, $describe);
    }

    private function processSingle(ThreeDThumbnailService $service, int $id, bool $force, bool $dryRun, bool $describe): int
    {
        $obj = DB::table('digital_object')->where('id', $id)->first();
        if (!$obj) {
            $this->error("Digital object {$id} not found.");
            return 1;
        }

        if (!$service->is3DModel($obj->name) && !$service->is3DMimeType($obj->mime_type ?? '')) {
            $this->error("Digital object {$id} ({$obj->name}) is not a 3D model.");
            return 1;
        }

        $multiAngleDir = $service->getMultiAngleDir($id);
        if (!$multiAngleDir) {
            $this->error("Cannot determine multi-angle directory for {$id}.");
            return 1;
        }

        if ($dryRun) {
            $existing = is_dir($multiAngleDir) ? count(glob($multiAngleDir . '/*.png')) : 0;
            $this->info("Would process: {$obj->name} (ID: {$id}, existing views: {$existing})");
            return 0;
        }

        // Check if already rendered (unless force)
        if (!$force && is_dir($multiAngleDir) && count(glob($multiAngleDir . '/*.png')) >= 6) {
            $this->warning("Multi-angle views already exist for {$obj->name}. Use --force to re-render.");
            if ($describe) {
                $this->outputDescription($multiAngleDir);
            }
            return 0;
        }

        $uploadsPath = PathResolver::getRootDir();
        $masterPath = $uploadsPath . $obj->path . $obj->name;

        if (!file_exists($masterPath)) {
            $this->error("Master file not found: {$masterPath}");
            return 1;
        }

        $this->info("Rendering 6 views: {$obj->name} (ID: {$id})...");
        $results = $service->generateMultiAngle($masterPath, $multiAngleDir);

        if (empty($results)) {
            $this->error("Failed to generate multi-angle renders.");
            return 1;
        }

        $this->success("Rendered " . count($results) . "/6 views → {$multiAngleDir}");

        if ($describe) {
            $this->outputDescription($multiAngleDir);
        }

        return 0;
    }

    private function processAll(ThreeDThumbnailService $service, bool $force, bool $dryRun, bool $describe): int
    {
        $extensions = ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'dae'];

        $objects = DB::table('digital_object as do')
            ->whereNull('do.parent_id')
            ->where(function ($q) use ($extensions, $service) {
                foreach ($extensions as $ext) {
                    $q->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
                foreach ($service->getSupportedMimeTypes() as $mime) {
                    $q->orWhere('do.mime_type', $mime);
                }
            })
            ->select('do.id', 'do.name', 'do.mime_type', 'do.path')
            ->get();

        if ($objects->isEmpty()) {
            $this->success('No 3D objects found.');
            return 0;
        }

        // Filter to only those missing multi-angle renders (unless force)
        $toProcess = [];
        foreach ($objects as $obj) {
            $dir = $service->getMultiAngleDir($obj->id);
            if ($force || !$dir || !is_dir($dir) || count(glob($dir . '/*.png')) < 6) {
                $toProcess[] = $obj;
            }
        }

        if (empty($toProcess)) {
            $this->success('All 3D objects already have multi-angle renders.');
            return 0;
        }

        $this->info('Found ' . count($toProcess) . ' 3D object(s) to render.');

        if ($dryRun) {
            $rows = [];
            foreach ($toProcess as $obj) {
                $dir = $service->getMultiAngleDir($obj->id);
                $existing = ($dir && is_dir($dir)) ? count(glob($dir . '/*.png')) : 0;
                $rows[] = [$obj->id, $obj->name, $obj->mime_type ?? '', $existing . '/6'];
            }
            $this->table(['ID', 'Name', 'MIME Type', 'Views'], $rows);
            return 0;
        }

        $processed = 0;
        $success = 0;
        $failed = 0;
        $uploadsPath = PathResolver::getRootDir();

        foreach ($toProcess as $obj) {
            $processed++;
            $this->line("Processing {$processed}/" . count($toProcess) . ": {$obj->name}... ", false);

            $masterPath = $uploadsPath . $obj->path . $obj->name;
            if (!file_exists($masterPath)) {
                $this->line('SKIP (file missing)');
                $failed++;
                continue;
            }

            $dir = $service->getMultiAngleDir($obj->id);
            if (!$dir) {
                $this->line('SKIP (no dir)');
                $failed++;
                continue;
            }

            try {
                $results = $service->generateMultiAngle($masterPath, $dir);
                if (!empty($results)) {
                    $this->line('Rendered ' . count($results) . ' views ... OK');
                    $success++;
                } else {
                    $this->line('FAILED');
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->line('ERROR: ' . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Done: {$success} succeeded, {$failed} failed out of {$processed} processed.");

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Output LLM description hint (image paths) to stdout.
     */
    private function outputDescription(string $multiAngleDir): void
    {
        $views = ['front', 'back', 'left', 'right', 'top', 'detail'];
        $this->info('Multi-angle render paths for LLM:');
        foreach ($views as $view) {
            $path = $multiAngleDir . '/' . $view . '.png';
            if (file_exists($path)) {
                $this->line("  {$view}: {$path} (" . number_format(filesize($path) / 1024, 1) . ' KB)');
            }
        }
        $this->comment('Use the web UI "describe object" voice command or POST to /ahgVoice/describeObject for LLM description.');
    }
}
