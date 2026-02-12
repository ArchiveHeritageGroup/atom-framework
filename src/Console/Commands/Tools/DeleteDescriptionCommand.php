<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Delete a description and its descendants.
 *
 * Ported from lib/task/tools/deleteDescriptionTask.class.php.
 * Uses Propel for nested set (MPTT) operations and cascade logic.
 */
class DeleteDescriptionCommand extends BaseCommand
{
    protected string $name = 'tools:delete-description';
    protected string $description = 'Delete an archival description and its descendants';
    protected string $detailedDescription = <<<'EOF'
Delete archival descriptions by slug.

If --repository is set, the slug refers to a repository whose top-level
descriptions will all be deleted. Otherwise, the slug refers to a single
information object which will be deleted along with its descendants.
EOF;

    private int $nDeleted = 0;
    private $resource;
    private string $resourceType;

    protected function configure(): void
    {
        $this->addArgument('slug', 'The slug of the description (or repository) to delete', true);
        $this->addOption('no-confirmation', 'B', 'Do not ask for confirmation');
        $this->addOption('repository', 'r', 'Delete descriptions under repository specified by slug');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $slug = $this->argument('slug');
        $this->resourceType = $this->hasOption('repository') ? 'QubitRepository' : 'QubitInformationObject';

        $this->fetchResource($slug);

        if (!$this->confirmDeletion()) {
            $this->info(sprintf('[%s] Task aborted.', date('g:i:s A')));
            return 0;
        }

        // Delete targeted information objects
        switch ($this->resourceType) {
            case 'QubitRepository':
                $this->deleteDescriptionsFromRepository();
                break;

            case 'QubitInformationObject':
                $this->deleteDescriptions($this->resource);
                break;
        }

        $this->success(sprintf(
            '[%s] Finished: %d descriptions deleted.',
            date('g:i:s A'),
            $this->nDeleted
        ));

        return 0;
    }

    private function confirmDeletion(): bool
    {
        if ($this->hasOption('no-confirmation')) {
            return true;
        }

        switch ($this->resourceType) {
            case 'QubitRepository':
                $confirmWarning = sprintf(
                    'WARNING: You are about to delete all the records under the repository "%s".',
                    $this->resource->getAuthorizedFormOfName(['cultureFallback' => true])
                );
                break;

            case 'QubitInformationObject':
                $confirmWarning = sprintf(
                    'WARNING: You are about to delete the record "%s" and %d descendant records.',
                    $this->resource->getTitle(['cultureFallback' => true]),
                    ($this->resource->rgt - $this->resource->lft - 1) / 2
                );
                break;
        }

        return $this->confirm($confirmWarning);
    }

    private function fetchResource(string $slug): void
    {
        $c = new \Criteria();
        $c->addJoin(
            constant("{$this->resourceType}::ID"),
            \QubitSlug::OBJECT_ID
        );
        $c->add(\QubitSlug::SLUG, $slug);

        $this->resource = call_user_func_array("{$this->resourceType}::getOne", [$c]);

        if (null === $this->resource) {
            throw new \RuntimeException(sprintf(
                'Resource (slug: %s, type: %s) not found in database.',
                $slug,
                $this->resourceType
            ));
        }
    }

    private function deleteDescriptions($root): void
    {
        $this->info(sprintf(
            '[%s] Deleting description "%s" (slug: %s, +%d descendants)',
            date('g:i:s A'),
            $root->getTitle(['cultureFallback' => true]),
            $root->slug,
            ($root->rgt - $root->lft - 1) / 2
        ));

        $conn = \Propel::getConnection();
        $conn->beginTransaction();

        try {
            $this->nDeleted += $root->deleteFullHierarchy();
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function deleteDescriptionsFromRepository(): void
    {
        $this->info(sprintf(
            '[%s] Removing descriptions from repository "%s" (slug: %s)...',
            date('g:i:s A'),
            $this->resource->getAuthorizedFormOfName(['cultureFallback' => true]),
            $this->resource->slug
        ));

        $rows = \QubitPdo::fetchAll(
            'SELECT id FROM information_object WHERE parent_id = ? AND repository_id = ?',
            [\QubitInformationObject::ROOT_ID, $this->resource->id]
        );

        foreach ($rows as $row) {
            $io = \QubitInformationObject::getById($row->id);
            if (null === $io) {
                throw new \RuntimeException(
                    "Failed to get information object {$row->id} in deleteDescriptionsFromRepository"
                );
            }

            $this->deleteDescriptions($io);
        }
    }
}
