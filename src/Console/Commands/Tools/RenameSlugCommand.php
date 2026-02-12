<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Rename a slug in the slug table.
 *
 * Updates the slug value for a given object, ensuring uniqueness.
 */
class RenameSlugCommand extends BaseCommand
{
    protected string $name = 'tools:rename-slug';
    protected string $description = 'Rename a slug';

    protected function configure(): void
    {
        $this->addArgument('old-slug', 'The current slug to rename', true);
        $this->addArgument('new-slug', 'The new slug value', true);
    }

    protected function handle(): int
    {
        $oldSlug = $this->argument('old-slug');
        $newSlug = $this->argument('new-slug');

        if ($oldSlug === $newSlug) {
            $this->warning('Old and new slugs are identical. Nothing to do.');
            return 0;
        }

        // Validate new slug format (alphanumeric, hyphens, lowercase)
        if (!preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $newSlug) && strlen($newSlug) > 1) {
            $this->error("Invalid slug format: '{$newSlug}'. Use lowercase alphanumeric characters and hyphens.");
            return 1;
        }

        // Check old slug exists
        $existing = DB::table('slug')->where('slug', $oldSlug)->first();
        if (!$existing) {
            $this->error("Slug '{$oldSlug}' not found.");
            return 1;
        }

        // Check new slug is not already taken
        $conflict = DB::table('slug')->where('slug', $newSlug)->first();
        if ($conflict) {
            $this->error("Slug '{$newSlug}' is already in use (object ID: {$conflict->object_id}).");
            return 1;
        }

        // Perform the rename
        $affected = DB::table('slug')
            ->where('slug', $oldSlug)
            ->update(['slug' => $newSlug]);

        if ($affected > 0) {
            $this->success("Slug renamed: '{$oldSlug}' -> '{$newSlug}' (object ID: {$existing->object_id}).");
        } else {
            $this->error('No rows updated. The slug may have been modified concurrently.');
            return 1;
        }

        return 0;
    }
}
