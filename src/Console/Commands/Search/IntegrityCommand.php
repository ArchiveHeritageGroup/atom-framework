<?php

namespace AtomFramework\Console\Commands\Search;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Services\Search\DescriptionIntegrityService;

/**
 * Find descriptions that would abort search:populate (#311).
 */
class IntegrityCommand extends BaseCommand
{
    protected string $name = 'search:integrity';
    protected string $description = 'Find descriptions the search indexer cannot load';
    protected string $detailedDescription = <<<'EOF'
Lists descriptions missing an object, slug or status row, and object rows left
behind without their description. Any one of these aborts search:populate for
the whole instance with "Couldn't find ancestors, please make sure parent_id
values are correct" - which blames parent_id, never the actual cause.

Read-only. Exit code 1 when anything is found, so it can gate a cron or deploy.

  php bin/atom search:integrity
EOF;

    protected function handle(): int
    {
        $problems = (new DescriptionIntegrityService())->findProblems();

        if (!$problems) {
            $this->success('No descriptions that would abort search:populate.');

            return 0;
        }

        $this->warning('These descriptions cannot be loaded by the search indexer:');
        foreach (self::reportLines($problems) as $line) {
            $this->line($line);
        }

        return 1;
    }

    /**
     * Shared with search:populate's pre-flight.
     *
     * @return string[]
     */
    public static function reportLines(array $problems): array
    {
        $lines = [];
        foreach ($problems as $p) {
            $more = $p['count'] > count($p['sample']) ? sprintf(' (first %d shown)', count($p['sample'])) : '';
            $lines[] = sprintf('  %6d  %s', $p['count'], $p['label']);
            $lines[] = '          ids: '.implode(', ', $p['sample']).$more;
        }
        $lines[] = '';
        $lines[] = 'Each is a missing row, not a parent_id fault; rebuilding the nested set will not help.';
        $lines[] = 'Create the missing slug/status rows (or remove the orphans), then re-run.';

        return $lines;
    }
}
