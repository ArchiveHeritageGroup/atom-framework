<?php

namespace AtomFramework\Console\Commands;

use AtomExtensions\Services\ThreeDThumbnailService;
use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Generate thumbnail derivatives for 3D model digital objects.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ThreeDDerivativesCommand extends BaseCommand
{
    protected string $name = '3d:derivatives';
    protected string $description = 'Generate thumbnail derivatives for 3D model files';
    protected string $detailedDescription = <<<'EOF'
Generate reference and thumbnail images for 3D models (GLB, GLTF, OBJ, STL, etc.)
using Blender rendering. Processes all 3D objects missing derivatives by default.

  <bold>php bin/atom 3d:derivatives</bold>             Process all missing
  <bold>php bin/atom 3d:derivatives --id=1406</bold>   Process specific digital object
  <bold>php bin/atom 3d:derivatives --dry-run</bold>   List objects without processing
  <bold>php bin/atom 3d:derivatives --force</bold>     Re-generate even if derivatives exist
EOF;

    protected function configure(): void
    {
        $this->addOption('id', null, 'Process a specific digital object ID');
        $this->addOption('slug', null, 'Process by information object slug');
        $this->addOption('force', 'f', 'Re-generate derivatives even if they exist');
        $this->addOption('dry-run', null, 'List 3D objects without processing');
    }

    protected function handle(): int
    {
        $service = new ThreeDThumbnailService();
        $id = $this->option('id');
        $slug = $this->option('slug');
        $force = $this->hasOption('force');
        $dryRun = $this->hasOption('dry-run');

        // Resolve slug to digital object ID
        if ($slug && !$id) {
            $id = $this->resolveSlug($slug);
            if (!$id) {
                $this->error("No 3D digital object found for slug: {$slug}");
                return 1;
            }
        }

        if ($id) {
            return $this->processSingle($service, (int) $id, $force, $dryRun);
        }

        return $this->processAll($service, $force, $dryRun);
    }

    private function processSingle(ThreeDThumbnailService $service, int $id, bool $force, bool $dryRun): int
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

        if ($dryRun) {
            $derivCount = DB::table('digital_object')->where('parent_id', $id)->count();
            $this->info("Would process: {$obj->name} (ID: {$id}, MIME: {$obj->mime_type}, derivatives: {$derivCount})");
            return 0;
        }

        $this->info("Processing: {$obj->name} (ID: {$id})...");

        if ($service->createDerivatives($id)) {
            $this->success("Derivatives generated for {$obj->name}");
            return 0;
        }

        $this->error("Failed to generate derivatives for {$obj->name}");
        return 1;
    }

    /**
     * Resolve a slug to a 3D digital object ID.
     */
    private function resolveSlug(string $slug): ?int
    {
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            return null;
        }

        $do = DB::table('digital_object')
            ->where('object_id', $slugRow->object_id)
            ->whereNull('parent_id')
            ->orderBy('id', 'desc')
            ->first();

        if ($do) {
            $this->info("Resolved slug '{$slug}' → digital object {$do->id} ({$do->name})");
            return (int) $do->id;
        }

        return null;
    }

    private function processAll(ThreeDThumbnailService $service, bool $force, bool $dryRun): int
    {
        $extensions = ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'dae'];

        $query = DB::table('digital_object as do')
            ->whereNull('do.parent_id')
            ->where(function ($q) use ($extensions, $service) {
                foreach ($extensions as $ext) {
                    $q->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
                foreach ($service->getSupportedMimeTypes() as $mime) {
                    $q->orWhere('do.mime_type', $mime);
                }
            })
            ->select('do.id', 'do.name', 'do.mime_type', 'do.path');

        if (!$force) {
            $query->leftJoin('digital_object as deriv', 'deriv.parent_id', '=', 'do.id')
                ->whereNull('deriv.id');
        }

        $objects = $query->get();

        if ($objects->isEmpty()) {
            $this->success('No 3D objects ' . ($force ? '' : 'without derivatives ') . 'found.');
            return 0;
        }

        $this->info('Found ' . count($objects) . ' 3D object(s) to process.');

        if ($dryRun) {
            $rows = [];
            foreach ($objects as $obj) {
                $derivCount = DB::table('digital_object')->where('parent_id', $obj->id)->count();
                $rows[] = [$obj->id, $obj->name, $obj->mime_type ?? '', $derivCount];
            }
            $this->table(['ID', 'Name', 'MIME Type', 'Derivatives'], $rows);
            return 0;
        }

        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($objects as $obj) {
            $processed++;
            $this->line("Processing {$processed}/" . count($objects) . ": {$obj->name}... ", false);

            try {
                if ($service->createDerivatives($obj->id)) {
                    $success++;
                    $this->line('OK');
                } else {
                    $failed++;
                    $this->line('FAILED');
                }
            } catch (\Exception $e) {
                $failed++;
                $this->line('ERROR: ' . $e->getMessage());
            }
        }

        $this->info("Done: {$success} succeeded, {$failed} failed out of {$processed} processed.");

        return $failed > 0 ? 1 : 0;
    }
}
