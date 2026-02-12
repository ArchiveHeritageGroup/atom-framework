<?php

namespace AtomFramework\Console\Commands\Propel;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Build all nested set values.
 *
 * Ported from lib/task/propel/propelBuildNestedSetTask.class.php.
 */
class BuildNestedSetCommand extends BaseCommand
{
    protected string $name = 'propel:build-nested-set';
    protected string $description = 'Build all nested set values';
    protected string $detailedDescription = <<<'EOF'
Build nested set values for information_object, term, and menu tables.
Optionally exclude tables with --exclude-tables (comma-separated).
Use --index to update the search index after rebuilding.
EOF;

    private array $children;
    private $conn;
    private bool $doIndex = false;

    protected function configure(): void
    {
        $this->addOption('exclude-tables', null, 'Exclude tables (comma-separated). Options: information_object, term, menu');
        $this->addOption('index', 'i', 'Update search index (defaults to false)');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $this->conn = $databaseManager->getDatabase('propel')->getConnection();

        $this->doIndex = $this->hasOption('index');

        $tables = [
            'information_object' => 'QubitInformationObject',
            'term' => 'QubitTerm',
            'menu' => 'QubitMenu',
        ];

        $excludeTables = [];
        $excludeOpt = $this->option('exclude-tables');
        if ($excludeOpt) {
            $excludeTables = array_map('trim', explode(',', $excludeOpt));
        }

        foreach ($tables as $table => $classname) {
            if (in_array($table, $excludeTables)) {
                $this->info('Skip nested set build for ' . $table . '.');
                continue;
            }

            $this->info('Build nested set for ' . $table . '...');

            $this->conn->beginTransaction();

            $sql = 'SELECT id, parent_id';
            $sql .= ' FROM ' . constant($classname . '::TABLE_NAME');
            $sql .= ' ORDER BY parent_id ASC, lft ASC';

            $this->children = [];

            foreach ($this->conn->query($sql, \PDO::FETCH_ASSOC) as $item) {
                if (isset($this->children[$item['parent_id']])) {
                    array_push($this->children[$item['parent_id']], $item['id']);
                } else {
                    $this->children[$item['parent_id']] = [$item['id']];
                }
            }

            $rootNode = [
                'id' => $classname::ROOT_ID,
                'lft' => 1,
                'rgt' => null,
            ];

            try {
                $this->recursivelyUpdateTree($rootNode, $classname);
            } catch (\PDOException $e) {
                $this->conn->rollback();
                throw new \RuntimeException($e->getMessage());
            }

            $this->conn->commit();
        }

        if (!$this->doIndex) {
            $this->warning(
                'Note: you will need to rebuild your search index for updates to show up properly in search results.'
            );
        }

        $this->success('Done!');

        return 0;
    }

    private function recursivelyUpdateTree(array $node, string $classname): int
    {
        $width = 2;
        $lft = $node['lft'];

        if (isset($this->children[$node['id']])) {
            ++$lft;

            foreach ($this->children[$node['id']] as $id) {
                $child = ['id' => $id, 'lft' => $lft, 'rgt' => null];

                $w0 = $this->recursivelyUpdateTree($child, $classname);
                $lft += $w0;
                $width += $w0;
            }

            unset($this->children[$node['id']]);
        }

        $node['rgt'] = $node['lft'] + $width - 1;

        $sql = 'UPDATE ' . $classname::TABLE_NAME;
        $sql .= ' SET lft = ' . $node['lft'];
        $sql .= ', rgt = ' . $node['rgt'];
        $sql .= ' WHERE id = ' . $node['id'] . ';';

        $this->conn->exec($sql);

        if ($this->doIndex) {
            if ($node['id'] != $classname::ROOT_ID) {
                $this->reindexLft($classname, $node['id'], $node['lft']);
            }
        }

        return $width;
    }

    private function reindexLft(string $classname, int $id, int $lft): void
    {
        \QubitSearch::getInstance()->partialUpdateById(
            $classname, $id, ['lft' => $lft]
        );
    }
}
