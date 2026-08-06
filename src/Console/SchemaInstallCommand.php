<?php

declare(strict_types=1);

namespace AtomFramework\Console;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Creates the database schema each enabled plugin needs.
 *
 * THE PROBLEM THIS SOLVES
 *
 * Plugin schema lives in three places. atom-framework/database/install.sql holds
 * 20 tables and is run by bin/install. atom-framework/database/migrations holds
 * a handful more and is run by "atom migrate run". And <plugin>/database/*.sql
 * holds around 1,200 CREATE TABLE statements across 250 files in 104 plugins,
 * which nothing has ever run.
 *
 * On an instance that grew over time this was invisible: the tables were created
 * by hand as each plugin was developed. On a fresh install it means a plugin
 * loads, renders, and then throws "Base table or view not found" the moment
 * anyone uses it. Measured 2026-08-06 on a clean AtoM 2.10.1: the audit trail
 * plugin was enabled and every page write failed against a missing
 * ahg_audit_chain_state.
 *
 * WHY IT IS NOT AS SIMPLE AS RUNNING THEM
 *
 * Those files are not a schema. They are years of accumulated incremental
 * patches, and running them in file order fails in three distinct ways:
 *
 *   - Cross-plugin dependency. ahgCorePlugin/install.sql references
 *     email_setting, which a different plugin creates.
 *   - Self dependency. The enum-to-dropdown patches need ahg_dropdown, which the
 *     install.sql that failed above them was supposed to create.
 *   - Already applied. Several ALTER statements duplicate columns that the
 *     migration runner has already added.
 *
 * So this runs in passes. Each pass executes every outstanding statement and
 * records which failed and why. Failures that mean "already done" are treated as
 * success and never retried. Failures that mean "something I need does not exist
 * yet" are retried on the next pass, because a later plugin may create it. When
 * a pass produces no further progress, the remaining failures are real and are
 * reported individually rather than swallowed.
 *
 * That converges without anyone having to work out the dependency order by hand,
 * and it stays correct as plugins are added.
 */
final class SchemaInstallCommand
{
    /**
     * MySQL errors meaning the object is already in the desired state.
     * Counted as success, never retried.
     */
    private const ALREADY_DONE = [
        1050,   // table exists
        1060,   // duplicate column
        1061,   // duplicate key
        1062,   // duplicate entry (seed data)
        1022,   // duplicate key
        1826,   // duplicate foreign key constraint name
        1359,   // trigger already exists
    ];

    /**
     * Errors meaning a dependency is missing. Retried on the next pass, because
     * a plugin later in the run may create what this one needs.
     */
    private const RETRYABLE = [
        1146,   // base table or view not found
        1054,   // unknown column
        1215,   // cannot add foreign key constraint
        1005,   // can't create table (usually a missing FK target)
        1091,   // can't drop; check that it exists
        1072,   // key column doesn't exist in table
    ];

    private string $rootDir;
    private bool $dryRun = false;
    private bool $verbose = false;

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function run(array $args): int
    {
        $only = null;
        $all = false;

        foreach ($args as $arg) {
            if (0 === strpos($arg, '--plugin=')) {
                $only = substr($arg, 9);
            } elseif ('--dry-run' === $arg) {
                $this->dryRun = true;
            } elseif ('--verbose' === $arg || '-v' === $arg) {
                $this->verbose = true;
            } elseif ('--all' === $arg) {
                $all = true;
            } elseif ('--help' === $arg || '-h' === $arg) {
                echo "Usage: atom schema:install [--plugin=ahgFooPlugin] [--all] [--dry-run] [-v]\n\n";
                echo "  Creates the tables enabled plugins need. --all covers every plugin\n";
                echo "  present on disk rather than only the enabled ones. --dry-run reports\n";
                echo "  what would run without touching the database.\n";

                return 0;
            }
        }

        $plugins = $this->targetPlugins($only, $all);

        if ($plugins === []) {
            echo "No plugins with schema to install.\n";

            return 0;
        }

        $statements = $this->collect($plugins);

        printf(
            "%d plugin%s, %d SQL file%s, %d statements.\n\n",
            count($plugins), count($plugins) === 1 ? '' : 's',
            $this->fileCount, $this->fileCount === 1 ? '' : 's',
            count($statements)
        );

        if ($this->dryRun) {
            foreach ($plugins as $plugin) {
                echo "  {$plugin}\n";
            }
            echo "\nDry run: nothing executed.\n";

            return 0;
        }

        return $this->execute($statements);
    }

    private int $fileCount = 0;

    /**
     * Which plugins to install schema for.
     */
    private function targetPlugins(?string $only, bool $all): array
    {
        $dir = $this->rootDir.'/atom-ahg-plugins';

        if (null !== $only) {
            return is_dir($dir.'/'.$only) ? [$only] : [];
        }

        $onDisk = array_map('basename', glob($dir.'/ahg*Plugin', GLOB_ONLYDIR) ?: []);

        if ($all) {
            return $onDisk;
        }

        try {
            $enabled = DB::table('atom_plugin')->where('is_enabled', 1)->pluck('name')->all();
        } catch (\Throwable $e) {
            echo "Could not read atom_plugin ({$e->getMessage()}); falling back to all plugins on disk.\n";

            return $onDisk;
        }

        return array_values(array_intersect($onDisk, $enabled));
    }

    /**
     * Every statement from every plugin's database directory, in a deterministic
     * order: install.sql first for each plugin, then the rest alphabetically.
     */
    private function collect(array $plugins): array
    {
        $statements = [];

        foreach ($plugins as $plugin) {
            $dir = $this->rootDir.'/atom-ahg-plugins/'.$plugin.'/database';

            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir.'/*.sql') ?: [];

            usort($files, static function ($a, $b) {
                $aInstall = 'install.sql' === basename($a);
                $bInstall = 'install.sql' === basename($b);

                if ($aInstall !== $bInstall) {
                    return $aInstall ? -1 : 1;
                }

                return strcmp($a, $b);
            });

            foreach ($files as $file) {
                ++$this->fileCount;

                foreach ($this->split((string) file_get_contents($file)) as $sql) {
                    $statements[] = [
                        'plugin' => $plugin,
                        'file' => basename($file),
                        'sql' => $sql,
                    ];
                }
            }
        }

        return $statements;
    }

    /**
     * Split a file into statements.
     *
     * Comments are stripped first so a semicolon inside one cannot end a
     * statement early. Anything using DELIMITER is left as a single unit,
     * because splitting a stored routine on its internal semicolons produces
     * fragments that cannot execute.
     */
    private function split(string $sql): array
    {
        if (false !== stripos($sql, 'DELIMITER')) {
            $trimmed = trim($sql);

            return '' === $trimmed ? [] : [$trimmed];
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = preg_replace('/^\s*#.*$/m', '', $sql);
        $sql = preg_replace('!/\*.*?\*/!s', '', (string) $sql);

        $parts = preg_split('/;\s*[\r\n]+/', (string) $sql) ?: [];

        $out = [];
        foreach ($parts as $part) {
            $part = trim(rtrim(trim($part), ';'));

            if ('' !== $part) {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * Run to a fixed point.
     */
    private function execute(array $statements): int
    {
        $pending = $statements;
        $applied = 0;
        $skipped = 0;
        $pass = 0;
        $failures = [];
        $hardFailures = [];

        $connection = DB::connection()->getPdo();

        // Buffered, or a SELECT inside a schema file leaves an unconsumed result
        // set and every statement after it dies with "Cannot execute queries
        // while other unbuffered queries are active". That failure is not about
        // the statement it is reported against, and it silently took out most of
        // the run the first time this was used.
        try {
            $connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (\Throwable $e) {
            // Not fatal; closeCursor below is the real guard.
        }

        while ($pending !== [] && $pass < 10) {
            ++$pass;
            $next = [];
            $progress = 0;
            $failures = [];

            foreach ($pending as $item) {
                try {
                    // prepare/execute/closeCursor rather than exec(), so a file
                    // containing a SELECT frees its result set instead of
                    // wedging the connection for everything that follows.
                    $statement = $connection->prepare($item['sql']);
                    $statement->execute();
                    $statement->closeCursor();
                    ++$applied;
                    ++$progress;
                } catch (\PDOException $e) {
                    $code = (int) ($e->errorInfo[1] ?? 0);

                    if (in_array($code, self::ALREADY_DONE, true)) {
                        ++$skipped;
                        ++$progress;

                        continue;
                    }

                    if (in_array($code, self::RETRYABLE, true)) {
                        $next[] = $item;
                        $failures[] = [$item, $e->getMessage(), $code];

                        continue;
                    }

                    // Anything else is a genuine error. Do not retry it, but keep
                    // it across passes: $failures is rebuilt each pass for the
                    // retryables, and a hard failure recorded only in pass 1
                    // would otherwise disappear from the final report.
                    $failures[] = [$item, $e->getMessage(), $code];
                    $hardFailures[] = [$item, $e->getMessage(), $code];
                }
            }

            printf(
                "  pass %d: %d applied, %d already present, %d deferred\n",
                $pass, $applied, $skipped, count($next)
            );

            // No forward movement means the remaining statements will never
            // succeed, so stop rather than spin.
            if (0 === $progress) {
                $pending = $next;

                break;
            }

            $pending = $next;
        }

        echo "\n";
        printf("Applied %d, already present %d.\n", $applied, $skipped);

        $allFailures = array_merge($hardFailures, $failures);

        if ($allFailures !== []) {
            printf("\n%d statement(s) could not be applied:\n", count($allFailures));

            $byPlugin = [];
            foreach ($allFailures as [$item, $message, $code]) {
                $byPlugin[$item['plugin'].'/'.$item['file']][] = [$code, $message];
            }

            foreach ($byPlugin as $where => $errors) {
                printf("  %s (%d)\n", $where, count($errors));

                if ($this->verbose) {
                    foreach ($errors as [$code, $message]) {
                        printf("      [%d] %s\n", $code, substr($message, 0, 140));
                    }
                } else {
                    printf("      [%d] %s\n", $errors[0][0], substr($errors[0][1], 0, 140));
                }
            }

            echo "\nRe-run with -v for every error. These are usually patches for\n";
            echo "a plugin that is not enabled, or superseded by the migration runner.\n";

            return 1;
        }

        echo "Schema complete.\n";

        return 0;
    }
}
