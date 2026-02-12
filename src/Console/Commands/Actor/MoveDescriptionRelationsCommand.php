<?php

namespace AtomFramework\Console\Commands\Actor;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Move actor-description relations.
 *
 * Ported from lib/task/actor/actorMoveDescriptionRelationsTask.class.php.
 */
class MoveDescriptionRelationsCommand extends BaseCommand
{
    protected string $name = 'actor:move-description-relations';
    protected string $description = 'Move actor-description relations';
    protected string $detailedDescription = <<<'EOF'
Move description relations from a source actor to a target actor,
including all events and name access point relations.
Use --skip-index to skip Elasticsearch indexing after the move.
EOF;

    protected function configure(): void
    {
        $this->addArgument('source', 'The slug of the source actor', true);
        $this->addArgument('target', 'The slug of the target actor', true);
        $this->addOption('skip-index', null, 'Skip Elasticsearch indexing');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        $sourceSlug = $this->argument('source');
        $targetSlug = $this->argument('target');
        $skipIndex = $this->hasOption('skip-index');

        if (null === $source = \QubitActor::getBySlug($sourceSlug)) {
            $this->error('An actor with slug "' . $sourceSlug . '" could not be found.');

            return 1;
        }

        if (null === $target = \QubitActor::getBySlug($targetSlug)) {
            $this->error('An actor with slug "' . $targetSlug . '" could not be found.');

            return 1;
        }

        $this->info(
            'Moving description relations from "'
            . $source->getAuthorizedFormOfName(['cultureFallback' => true])
            . '" to "'
            . $target->getAuthorizedFormOfName(['cultureFallback' => true])
            . '" ...'
        );

        // Amalgamate related description IDs before update
        $relatedIoIds = [];
        if (!$skipIndex) {
            $sql = "SELECT event.object_id FROM event
                JOIN object ON event.object_id=object.id
                WHERE event.actor_id=:sourceId
                AND object.class_name='QubitInformationObject'
                UNION ALL
                SELECT relation.subject_id FROM relation
                JOIN object ON relation.subject_id=object.id
                WHERE relation.object_id=:sourceId
                AND relation.type_id=:typeId
                AND object.class_name='QubitInformationObject'";
            $params = [
                ':sourceId' => $source->id,
                ':typeId' => \QubitTerm::NAME_ACCESS_POINT_ID,
            ];
            $relatedIoIds = \QubitPdo::fetchAll(
                $sql,
                $params,
                ['fetchMode' => \PDO::FETCH_COLUMN]
            );
        }

        // Move all events
        $sql = "UPDATE event
            JOIN object ON event.object_id=object.id
            SET event.actor_id=:targetId
            WHERE event.actor_id=:sourceId
            AND object.class_name='QubitInformationObject'";
        $params = [':targetId' => $target->id, ':sourceId' => $source->id];
        $updatedCount = \QubitPdo::modify($sql, $params);

        // Move name access point relations
        $sql = "UPDATE relation
            JOIN object ON relation.subject_id=object.id
            SET relation.object_id=:targetId
            WHERE relation.object_id=:sourceId
            AND relation.type_id=:typeId
            AND object.class_name='QubitInformationObject'";
        $params = [
            ':targetId' => $target->id,
            ':sourceId' => $source->id,
            ':typeId' => \QubitTerm::NAME_ACCESS_POINT_ID,
        ];
        $updatedCount += \QubitPdo::modify($sql, $params);

        $this->line($updatedCount . ' description relations moved.');

        // Update Elasticsearch index
        if (!$skipIndex) {
            $this->info('Updating Elasticsearch index ...');

            $search = \QubitSearch::getInstance();
            $search->update($source);
            $search->update($target);
            foreach ($relatedIoIds as $id) {
                $search->update(\QubitInformationObject::getById($id));
            }
        } else {
            $this->warning(
                'The Elasticsearch index has not been updated. Please run search:populate manually.'
            );
        }

        $this->success('Done!');

        return 0;
    }
}
