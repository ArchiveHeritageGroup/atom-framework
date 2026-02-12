<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Unlink creators from descriptions so creator inheritance can be used.
 *
 * Ported from lib/task/tools/unlinkCreatorTask.class.php.
 * Uses Propel for complex relation operations involving object graphs
 * and event-based cascade logic.
 */
class UnlinkCreatorCommand extends BaseCommand
{
    protected string $name = 'tools:unlink-creator';
    protected string $description = 'Unlink creators from descriptions so creator inheritance can be used';
    protected string $detailedDescription = <<<'EOF'
Unlink creators from descriptions so creator inheritance can be used.

This task will examine a description's creators and compare them to the
description's ancestors. If identical creators are found on an ancestor
description such that creator inheritance could be used instead of directly
linking a creator to a description, the creator will be unlinked from the
description.

You must supply either --creator-slug or --description-slug, but not both.
EOF;

    private $actor;

    protected function configure(): void
    {
        $this->addOption('creator-slug', null, 'Restrict changes to specific creator');
        $this->addOption('description-slug', null, 'Restrict changes to this information object hierarchy');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $creatorSlug = $this->option('creator-slug');
        $descriptionSlug = $this->option('description-slug');

        if ($creatorSlug && $descriptionSlug) {
            throw new \RuntimeException(
                'Creator and description filters cannot be set at the same time. Remove one and try again.'
            );
        }

        $criteria = $this->getCriteria($creatorSlug, $descriptionSlug);
        $this->unlinkCreators($criteria);

        $this->success('Done!');

        return 0;
    }

    private function getCriteria(?string $creatorSlug, ?string $descriptionSlug): \Criteria
    {
        $ioList = null;
        $this->actor = null;

        // Get actor record from slug if supplied
        if ($creatorSlug) {
            $criteria = new \Criteria();
            $criteria->addJoin(\QubitActor::ID, \QubitSlug::OBJECT_ID);
            $criteria->add(\QubitSlug::SLUG, $creatorSlug);
            $this->actor = \QubitActor::getOne($criteria);

            if (null === $this->actor) {
                throw new \RuntimeException('Actor slug supplied but not found');
            }
        }

        // Get IO from slug if supplied
        $io = null;
        if ($descriptionSlug) {
            $criteria = new \Criteria();
            $criteria->addJoin(\QubitInformationObject::ID, \QubitSlug::OBJECT_ID);
            $criteria->add(\QubitSlug::SLUG, $descriptionSlug);
            $io = \QubitInformationObject::getOne($criteria);

            if (null === $io) {
                throw new \RuntimeException('Description slug supplied but not found');
            }

            // Get ALL descendants because we are fixing all Creators for this IO
            $ioList = [];
            foreach ($io->descendants->andSelf()->orderBy('lft') as $item) {
                $ioList[] = $item->id;
            }
        }

        // Get affected IO records via event table
        $criteria = new \Criteria();
        $criteria->addJoin(\QubitInformationObject::ID, \QubitEvent::OBJECT_ID);
        $criteria->addJoin(\QubitActor::ID, \QubitEvent::ACTOR_ID);
        $criteria->addGroupByColumn(\QubitInformationObject::ID);

        // Limit to a specific actor
        if (null !== $this->actor) {
            $criteria->add(\QubitActor::ID, $this->actor->id, \Criteria::EQUAL);
        }

        // Limit to specific information object hierarchy
        if (null !== $ioList) {
            $criteria->add(\QubitInformationObject::ID, $ioList, \Criteria::IN);
        }

        return $criteria;
    }

    private function unlinkCreators(\Criteria $criteria): void
    {
        // Loop over hierarchy of this Information Object from the top down.
        // Higher levels of IO must be corrected before lower nodes.
        foreach (\QubitInformationObject::get($criteria)->orderBy('lft') as $io) {
            $deleteCreators = false;
            $creatorIds = [];
            $ancestorCreatorIds = [];

            $this->info(sprintf('Description: %s %d', $io->slug, $io->id));

            $creators = $io->getCreators();
            foreach ($creators as $creator) {
                $creatorIds[] = $creator->id;
            }

            // Nothing to do if this is the top level record or if no creators on this IO
            if (\QubitInformationObject::ROOT_ID == $io->parentId || 0 == count($creatorIds)) {
                continue;
            }

            // If an actor was specified as params, that is the only actor we can remove.
            // If > 1 actor on this IO, we can't remove only one or the inheritance
            // will not work properly, so skip.
            if (null !== $this->actor && 1 < count($creatorIds)) {
                continue;
            }

            // Get all ancestors of this IO and iterate from bottom up
            foreach ($io->ancestors->andSelf()->orderBy('rgt') as $ancestor) {
                // If this ancestor is the root IO or self, skip it
                if (\QubitInformationObject::ROOT_ID == $ancestor->id || $ancestor->id == $io->id) {
                    continue;
                }

                $ancestorCreators = $ancestor->getCreators();
                $this->comment(sprintf('  Ancestor: %s', $ancestor->slug));

                // Creator list must match exactly. Test count, and if equal, look closer
                if (count($ancestorCreators) == count($creators)) {
                    $ancestorCreatorIds = [];
                    foreach ($ancestorCreators as $ancestorCreator) {
                        $ancestorCreatorIds[] = $ancestorCreator->id;
                    }

                    $diff = array_diff($creatorIds, $ancestorCreatorIds);
                    // If the creator lists match exactly, then delete and inherit from ancestor
                    if (0 == count($diff)) {
                        $deleteCreators = true;
                        break;
                    }

                    // Creators on ancestors but they don't match: stop looking
                    break;
                }

                // If there are creators on the ancestors but they don't match: stop
                if (count($ancestorCreators) > 0) {
                    break;
                }
            }

            if ($deleteCreators) {
                $this->removeCreator($creatorIds, $io);
            }
        }
    }

    private function removeCreator(array $creatorIds, $infoObj): void
    {
        // Unlink this Actor from all creation events on this IO
        foreach ($infoObj->getActorEvents(['eventTypeId' => \QubitTerm::CREATION_ID]) as $event) {
            if (in_array($event->actor->id, $creatorIds)) {
                $this->line(sprintf('  Unlink: %s', $event->actor->slug));
                $event->indexOnSave = true;
                unset($event->actor);
                $event->save();

                // Delete the event record too if there aren't any dates/times on it
                if (
                    null == $event->getPlace()->name && null == $event->date
                    && null == $event->name && null == $event->description
                    && null == $event->startDate && null == $event->endDate
                    && null == $event->startTime && null == $event->endTime
                ) {
                    $event->delete();
                }
            }
        }
    }
}
