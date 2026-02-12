<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Regenerate digital object derivatives from master copy.
 *
 * Ported from lib/task/digitalobject/digitalObjectRegenDerivativesTask.class.php.
 */
class DigitalObjectRegenDerivativesCommand extends BaseCommand
{
    protected string $name = 'digitalobject:regen-derivatives';
    protected string $description = 'Regenerate digital object derivative from master copy';
    protected string $detailedDescription = <<<'EOF'
Regenerate digital object derivatives from master copy.
Use --slug to limit to a specific resource and its descendants.
Use --type to regenerate only reference or thumbnail derivatives.
EOF;

    private array $validTypes = ['reference', 'thumbnail'];

    protected function configure(): void
    {
        $this->addOption('slug', 'l', 'Information object or actor slug');
        $this->addOption('type', 'd', 'Derivative type ("reference" or "thumbnail")');
        $this->addOption('media-type', null, 'Limit to a specific media type (audio, image, text, video)');
        $this->addOption('index', 'i', 'Update search index (defaults to false)');
        $this->addOption('force', 'f', 'No confirmation message');
        $this->addOption('only-externals', 'o', 'Only external objects');
        $this->addOption('json', 'j', 'Limit to IDs in a JSON file');
        $this->addOption('skip-to', null, 'Skip until a certain filename is encountered');
        $this->addOption('no-overwrite', null, 'Do not overwrite existing derivatives (and no confirmation)');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $timer = new \QubitTimer();
        $skip = true;

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $conn = $databaseManager->getDatabase('propel')->getConnection();

        $type = $this->option('type');
        $mediaType = $this->option('media-type');
        $slug = $this->option('slug');
        $doIndex = $this->hasOption('index');
        $force = $this->hasOption('force');
        $onlyExternals = $this->hasOption('only-externals');
        $json = $this->option('json');
        $skipTo = $this->option('skip-to');
        $noOverwrite = $this->hasOption('no-overwrite');

        // Validate 'type' value
        if ($type && !in_array($type, $this->validTypes)) {
            $this->error(sprintf(
                'Invalid value for "type", must be one of (%s)',
                implode(',', $this->validTypes)
            ));

            return 1;
        }

        // Validate "media-type" value
        $validMediaTypes = [
            'audio' => \QubitTerm::AUDIO_ID,
            'image' => \QubitTerm::IMAGE_ID,
            'text' => \QubitTerm::TEXT_ID,
            'video' => \QubitTerm::VIDEO_ID,
        ];

        if ($mediaType && !array_key_exists($mediaType, $validMediaTypes)) {
            $this->error(sprintf(
                'Invalid value for "media-type", must be one of (%s)',
                implode(',', array_keys($validMediaTypes))
            ));

            return 1;
        }

        if ($doIndex) {
            \QubitSearch::enable();
        } else {
            \QubitSearch::disable();
        }

        // Get all master digital objects
        $query = 'SELECT do.id
            FROM digital_object do JOIN object o ON do.object_id = o.id
            LEFT JOIN information_object io ON o.id=io.id';
        $whereClauses = [];

        // Limit to a resource (and descendants if an information object)
        if ($slug) {
            $q2 = 'SELECT o.id, o.class_name
                FROM object o JOIN slug ON o.id = slug.object_id
                WHERE slug.slug = ?';

            $row = \QubitPdo::fetchOne($q2, [$slug]);

            if (false === $row) {
                $this->error('Invalid slug');

                return 1;
            }

            switch ($row->class_name) {
                case 'QubitInformationObject':
                    $io = \QubitInformationObject::getById($row->id);
                    $whereClauses[] = sprintf('io.lft >= %d AND io.lft <= %d', $io->lft, $io->rgt);
                    break;

                case 'QubitActor':
                    $whereClauses[] = sprintf('o.id = %d', $row->id);
                    break;

                default:
                    $this->error('Invalid slug');

                    return 1;
            }
        }

        if ($onlyExternals) {
            $whereClauses[] = sprintf('do.usage_id = %d', \QubitTerm::EXTERNAL_URI_ID);
        }

        if ($mediaType) {
            $whereClauses[] = sprintf('do.media_type_id = %d', $validMediaTypes[$mediaType]);
        }

        if ($json) {
            $ids = json_decode(file_get_contents($json));
            $whereClauses[] = 'do.id IN (' . implode(', ', $ids) . ')';
        }

        if ($noOverwrite) {
            $query .= ' LEFT JOIN digital_object child ON do.id = child.parent_id';
            $whereClauses[] = 'do.parent_id IS NULL AND child.id IS NULL';
        }

        // Final confirmation (skip if no-overwrite)
        if (!$force && !$noOverwrite) {
            $changed = $mediaType ?: 'ALL';

            if ($slug) {
                $msg = sprintf(
                    'Continuing will regenerate the derivatives for %s digital objects (and descendants of, if an information object) "%s". This will PERMANENTLY DELETE existing derivatives you chose to regenerate.',
                    $changed,
                    $slug
                );
            } else {
                $msg = sprintf(
                    'Continuing will regenerate the derivatives for %s digital objects. This will PERMANENTLY DELETE existing derivatives you chose to regenerate.',
                    $changed
                );
            }

            if (!$this->confirm($msg)) {
                $this->info('Bye!');

                return 0;
            }
        }

        // Add WHERE clauses to SQL query
        if (count($whereClauses)) {
            $query .= sprintf(' WHERE %s', implode(' AND ', $whereClauses));
        }

        $query .= ' AND do.usage_id != ' . \QubitTerm::OFFLINE_ID;

        // Do work
        foreach (\QubitPdo::fetchAll($query) as $item) {
            $do = \QubitDigitalObject::getById($item->id);

            if (null == $do) {
                continue;
            }

            if ($skipTo) {
                if ($do->name != $skipTo && $skip) {
                    $this->line('Skipping ' . $do->name);
                    continue;
                }
                $skip = false;
            }

            $this->line(sprintf(
                'Regenerating derivatives for %s... (%ss)',
                $do->name,
                $timer->elapsed()
            ));

            try {
                \digitalObjectRegenDerivativesTask::regenerateDerivatives($do, [
                    'type' => $type,
                    'index' => $doIndex,
                ]);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if (!$doIndex) {
            $this->warning('Please update the search index manually to reflect any changes');
        }

        $this->success('Done!');

        return 0;
    }
}
