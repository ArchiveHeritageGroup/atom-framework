<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Delete digital objects given an archival description slug.
 *
 * Ported from lib/task/digitalobject/digitalObjectDeleteTask.class.php.
 */
class DigitalObjectDeleteCommand extends BaseCommand
{
    protected string $name = 'digitalobject:delete';
    protected string $description = 'Delete digital objects given an archival description slug';
    protected string $detailedDescription = <<<'EOF'
Delete digital objects by slug. Slug must be an information object, or a
repository. Use --and-descendants to delete digital objects for descendant
archival descriptions as well.
EOF;

    private array $validMediaTypes;

    protected function configure(): void
    {
        $this->addArgument('slug', 'Slug of the description or repository', true);
        $this->addOption('and-descendants', null, 'Remove digital objects for descendant descriptions as well');
        $this->addOption('media-type', null, 'Limit deletion to a specific media type (audio, image, text, video)');
        $this->addOption('dry-run', 'd', 'Dry run (no database changes)');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $this->validMediaTypes = [
            'audio' => \QubitTerm::AUDIO_ID,
            'image' => \QubitTerm::IMAGE_ID,
            'text' => \QubitTerm::TEXT_ID,
            'video' => \QubitTerm::VIDEO_ID,
        ];

        $slug = $this->argument('slug');
        $nDeleted = 0;
        $objectIds = [];

        $t = new \QubitTimer();

        // Remind user they are in dry run mode
        if ($this->hasOption('dry-run')) {
            $this->warning('DRY RUN (no changes will be made to the database)');
        }

        $mediaType = $this->option('media-type');
        if ($mediaType && !array_key_exists($mediaType, $this->validMediaTypes)) {
            $this->error(sprintf(
                'Invalid value for "media-type", must be one of (%s)',
                implode(',', array_keys($this->validMediaTypes))
            ));

            return 1;
        }

        $sql = "SELECT slug.object_id, object.class_name
            FROM slug
            JOIN object ON object.id = slug.object_id
            WHERE slug.slug = '" . $slug . "'";

        $statement = \QubitPdo::prepareAndExecute($sql);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !$row['object_id']) {
            $this->error('Invalid slug "' . $slug . '" entered.');

            return 1;
        }

        if (!in_array($row['class_name'], ['QubitInformationObject', 'QubitRepository'])) {
            $this->error('Invalid slug with object type "' . $row['class_name'] . '" entered.');

            return 1;
        }

        $informationObject = null;
        $repository = null;

        if ('QubitInformationObject' == $row['class_name']) {
            $informationObject = \QubitInformationObject::getById($row['object_id']);
            if (null === $informationObject) {
                $this->error('Failed to fetch information object with the slug given.');

                return 1;
            }
        }

        if ('QubitRepository' == $row['class_name']) {
            $repository = \QubitRepository::getById($row['object_id']);
            if (null === $repository) {
                $this->error('Failed to fetch repository with the slug given.');

                return 1;
            }
        }

        switch ($row['class_name']) {
            case 'QubitInformationObject':
                $objectIds = $this->hasOption('and-descendants')
                    ? $this->getIoDescendantIds($informationObject->lft, $informationObject->rgt)
                    : [$informationObject->id];
                break;

            case 'QubitRepository':
                $sql = 'SELECT id, lft, rgt FROM ' . \QubitInformationObject::TABLE_NAME . ' WHERE repository_id=:repository_id';
                $params = [':repository_id' => $repository->id];
                $relatedInformationObjects = \QubitPdo::fetchAll($sql, $params, ['fetchMode' => \PDO::FETCH_ASSOC]);

                foreach ($relatedInformationObjects as $io) {
                    $objectIds = array_merge($objectIds, $this->getIoDescendantIds($io['lft'], $io['rgt']));
                }
                break;
        }

        foreach ($objectIds as $id) {
            if (null !== $object = \QubitInformationObject::getById($id)) {
                $success = $this->deleteDigitalObject($object, $mediaType);

                $this->line(sprintf(
                    '(%d of %d) %s: %s',
                    $success ? ++$nDeleted : $nDeleted,
                    count($objectIds),
                    $success ? 'deleting digital object for' : 'nothing to delete',
                    $object->getTitle(['cultureFallback' => true])
                ));
            }
            \Qubit::clearClassCaches();
        }

        $this->success(sprintf('%d digital objects deleted (%.2fs elapsed)', $nDeleted, $t->elapsed()));

        return 0;
    }

    private function getIoDescendantIds(int $lft, int $rgt): array
    {
        $sql = 'SELECT io.id
            FROM ' . \QubitInformationObject::TABLE_NAME . ' io
            WHERE io.lft >= :lft
            AND io.rgt <= :rgt
            ORDER BY io.lft ASC';

        $params = [':lft' => $lft, ':rgt' => $rgt];

        return \QubitPdo::fetchAll($sql, $params, ['fetchMode' => \PDO::FETCH_COLUMN]);
    }

    private function deleteDigitalObject($object, ?string $mediaType): bool
    {
        foreach ($object->digitalObjectsRelatedByobjectId as $do) {
            if (!$mediaType || ($mediaType && $do->mediaTypeId == $this->validMediaTypes[$mediaType])) {
                if (!$this->hasOption('dry-run')) {
                    $do->delete();
                }

                return true;
            }
        }

        return false;
    }
}
