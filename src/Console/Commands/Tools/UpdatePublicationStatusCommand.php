<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Update publication status of archival descriptions.
 *
 * Ported from lib/task/tools/updatePublicationStatusTask.class.php.
 * Uses Propel for complex publication status updates involving nested
 * set traversal and Propel object saves, plus Elasticsearch partial updates.
 */
class UpdatePublicationStatusCommand extends BaseCommand
{
    protected string $name = 'tools:update-publication-status';
    protected string $description = 'Update publication status of archival descriptions';
    protected string $detailedDescription = <<<'EOF'
Update the publication status of either an individual description or,
if the --repo option is used, all of the descriptions in a repository.

Descendants of updated descriptions will also be updated unless the
--ignore-descendants option is used.

Examples:
    php bin/atom tools:update-publication-status published my-description-slug
    php bin/atom tools:update-publication-status draft my-description-slug --ignore-descendants
    php bin/atom tools:update-publication-status published my-repo-slug --repo
EOF;

    private int $failureCount = 0;

    protected function configure(): void
    {
        $this->addArgument('publicationStatus', 'Desired publication status [draft|published]', true);
        $this->addArgument('slug', 'Resource slug', true);
        $this->addOption('ignore-descendants', 'i', 'Do not update descendants');
        $this->addOption('no-confirm', 'y', 'No confirmation message');
        $this->addOption('repo', 'r', 'Update all descriptions in given repository');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $pubStatusArg = $this->argument('publicationStatus');
        $slug = $this->argument('slug');

        $criteria = new \Criteria();
        $criteria->add(\QubitSlug::SLUG, $slug);
        $criteria->addJoin(\QubitSlug::OBJECT_ID, \QubitObject::ID);

        if (!$this->hasOption('repo')) {
            $resource = \QubitInformationObject::get($criteria)->__get(0);
        } else {
            $resource = \QubitRepository::get($criteria)->__get(0);
        }

        $publicationStatusId = $this->getPublicationStatusIdByName($pubStatusArg);
        $publicationStatus = \QubitTerm::getById($publicationStatusId);

        // Check if the resource exists
        if (!isset($resource)) {
            throw new \RuntimeException('Resource not found');
        }

        // Check if the given status is correct and exists
        if (!isset($publicationStatus)) {
            throw new \RuntimeException('Publication status not found');
        }

        if (\QubitTaxonomy::PUBLICATION_STATUS_ID != $publicationStatus->taxonomyId) {
            throw new \RuntimeException('Given term is not part of the publication status taxonomy');
        }

        // Final confirmation
        if (!$this->hasOption('no-confirm')) {
            $question = sprintf(
                'Please confirm that you want to change the publication status of "%s" to "%s"',
                $resource->__toString(),
                $publicationStatus->__toString()
            );

            if (!$this->confirm($question)) {
                $this->info('Bye!');
                return 1;
            }
        }

        // Do work
        if (!$this->hasOption('repo')) {
            $this->updatePublicationStatus($resource, $publicationStatus);

            if (!$this->hasOption('ignore-descendants')) {
                $this->updatePublicationStatusDescendants($resource, $publicationStatus);
            }
        } else {
            $criteria = new \Criteria();
            $criteria->add(\QubitInformationObject::REPOSITORY_ID, $resource->id);

            foreach (\QubitInformationObject::get($criteria) as $item) {
                $this->updatePublicationStatus($item, $publicationStatus);

                if (!$this->hasOption('ignore-descendants')) {
                    $this->updatePublicationStatusDescendants($item, $publicationStatus);
                }
            }
        }

        if (!empty($this->failureCount)) {
            $this->warning(sprintf(
                'Indexing failures occurred when updating publication status. %d records were not updated.',
                $this->failureCount
            ));
        }

        $this->newline();
        $this->success('Finished updating publication statuses');

        return 0;
    }

    private function updatePublicationStatus($resource, $publicationStatus): void
    {
        $resource->indexOnSave = false;
        $resource->setPublicationStatus($publicationStatus->id);
        $resource->save();

        \QubitSearch::getInstance()->partialUpdate(
            $resource,
            ['publicationStatusId' => $publicationStatus->id]
        );
    }

    private function updatePublicationStatusDescendants($resource, $publicationStatus): void
    {
        $sql = 'UPDATE status
            JOIN information_object io ON status.object_id = io.id
            SET status.status_id = :publicationStatus
            WHERE status.type_id = :publicationStatusType
            AND io.lft > :lft
            AND io.rgt < :rgt';

        $params = [
            ':publicationStatus' => $publicationStatus->id,
            ':publicationStatusType' => \QubitTerm::STATUS_TYPE_PUBLICATION_ID,
            ':lft' => $resource->lft,
            ':rgt' => $resource->rgt,
        ];

        \QubitPdo::modify($sql, $params);

        // Use updateByQuery to update publication status in ES for resource
        $query = new \Elastica\Query\Term();
        $query->setTerm('ancestors', $resource->id);

        $queryScript = \Elastica\Script\AbstractScript::create([
            'script' => [
                'source' => 'ctx._source.publicationStatusId = ' . $publicationStatus->id,
                'lang' => 'painless',
            ],
        ]);

        $options = ['conflicts' => 'proceed'];

        $response = \QubitSearch::getInstance()->index->getIndex('QubitInformationObject')
            ->updateByQuery($query, $queryScript, $options)
            ->getData();

        if (!empty($response['failures'])) {
            $this->failureCount += count($response['failures']);
        }
    }

    private function getPublicationStatusIdByName(string $pubStatus): int
    {
        $sql = 'SELECT t.id FROM term t JOIN term_i18n ti ON t.id = ti.id
            WHERE t.taxonomy_id = ? AND ti.name = ?';

        $pubStatusId = \QubitPdo::fetchColumn($sql, [\QubitTaxonomy::PUBLICATION_STATUS_ID, $pubStatus]);
        if (!$pubStatusId) {
            throw new \RuntimeException("Invalid publication status specified: {$pubStatus}");
        }

        return (int) $pubStatusId;
    }
}
