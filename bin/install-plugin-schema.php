<?php

/**
 * Load a packaged plugin's schema, safely.
 *
 * WHY THIS EXISTS
 *
 * INSTALL.md used to say:
 *
 *     mysql -u <user> -p <database> < plugins/<plugin>/database/install.sql
 *
 * The mysql client stops at the first error and exits, so one failing statement
 * silently abandons every statement after it. Measured on ahgProvenancePlugin:
 * a foreign key that could not resolve aborted the file at line 226 and left
 * **7 of its 9 tables created**, with no summary and an exit status most people
 * never check. The instance then looks installed and fails later, somewhere else,
 * on a missing table.
 *
 * The framework's `schema:install` already solves this by running statements
 * individually in convergence passes, but a plugin installed from a zip has no
 * framework to run. This is that algorithm as a single dependency-free file that
 * ships inside the bundle.
 *
 * WHAT IT DOES DIFFERENTLY
 *
 *   - runs each statement separately, so one failure cannot hide the rest
 *   - retries statements whose error means "a dependency is not there yet",
 *     until a pass makes no further progress, so file order stops mattering
 *   - treats "already exists" as success, so re-running is safe
 *   - verifies the tables extension.json declares actually exist at the end,
 *     which is the check that would have caught the failure above
 *   - exits non-zero when anything is genuinely wrong
 *
 * Usage, from the AtoM root:
 *
 *   php plugins/<plugin>/bin/install-plugin-schema.php \
 *       --database=atom --user=atom --password=secret [--host=localhost] [--dry-run]
 *
 * With no --plugin it installs every plugin directory that sits beside it.
 */

$opt = getopt('', ['database:', 'user:', 'password::', 'password-file::', 'host::', 'socket::', 'plugin::', 'dry-run', 'quiet', 'force']);

foreach (['database', 'user'] as $required) {
    if (empty($opt[$required])) {
        fwrite(STDERR, "usage: php install-plugin-schema.php --database=DB --user=USER [--password=PW] [--plugin=NAME] [--dry-run] [--force]\n");
        exit(2);
    }
}

$password = isset($opt['password-file'])
    ? trim((string) file_get_contents($opt['password-file']))
    : (string) ($opt['password'] ?? '');

$host = $opt['host'] ?? 'localhost';
$dryRun = isset($opt['dry-run']);
$quiet = isset($opt['quiet']);

/** MySQL errors meaning the object is already in the desired state. */
const ALREADY_DONE = [1050, 1060, 1061, 1062, 1022, 1826, 1359];

/** Errors meaning a dependency is missing; retried while passes make progress. */
const RETRYABLE = [1146, 1054, 1215, 1005, 1091, 1072, 1824];

function say(string $line): void
{
    global $quiet;
    if (!$quiet) {
        echo $line."\n";
    }
}

/**
 * Split SQL into statements.
 *
 * Comment lines are dropped first so a semicolon inside one cannot split a
 * statement in half. Routine bodies (DELIMITER blocks) are not supported here -
 * a plugin shipping one needs the full installer.
 */
function statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);

    $raw = [];
    foreach (preg_split('/;\s*\n/', $sql) as $chunk) {
        $chunk = trim($chunk, "; \t\n\r");
        if ('' !== $chunk) {
            $raw[] = $chunk;
        }
    }

    // Keep a session-state sequence together as one unit.
    //
    // install.sql files add conditional foreign keys with
    // SET @sql = ... / PREPARE stmt FROM @sql / EXECUTE stmt / DEALLOCATE PREPARE stmt.
    // Those four share session state, so retrying one of them on a later pass
    // orphans the rest: the PREPARE gets deferred, the EXECUTE runs without it and
    // fails with "Unknown prepared statement handler". Grouped, they either all
    // run in order or all defer together.
    $out = [];
    $group = [];

    foreach ($raw as $stmt) {
        $isSequence = (bool) preg_match('/^\s*(SET\s+@|PREPARE\s|EXECUTE\s|DEALLOCATE\s+PREPARE)/i', $stmt);

        if ($isSequence) {
            $group[] = $stmt;
            continue;
        }

        if ($group) {
            $out[] = implode(";\n", $group);
            $group = [];
        }

        $out[] = $stmt;
    }

    if ($group) {
        $out[] = implode(";\n", $group);
    }

    return $out;
}

/**
 * Tables a plugin claims in extension.json.
 */
function declaredTables(string $dir): array
{
    $manifest = $dir.'/extension.json';

    if (!is_file($manifest)) {
        return [];
    }

    $json = json_decode((string) file_get_contents($manifest), true);

    return is_array($json['tables'] ?? null) ? $json['tables'] : [];
}

/**
 * Dependencies named in extension.json that are not installed, or null if all are.
 *
 * "Installed" is judged on the database, not the filesystem: a plugin directory
 * sitting on disk unenabled and with no tables is not a dependency being met.
 * Where a dependency owns no tables at all - a template or helper plugin - its
 * presence on disk is the only thing that can be checked, so that is what is
 * used.
 */
function unmetDependencies(string $dir, array $roots, PDO $pdo): ?array
{
    $manifest = $dir.'/extension.json';

    if (!is_file($manifest)) {
        return null;
    }

    $json = json_decode((string) file_get_contents($manifest), true);
    $deps = is_array($json['dependencies'] ?? null) ? $json['dependencies'] : [];

    if (!$deps) {
        return null;
    }

    $missing = [];

    foreach ($deps as $dep) {
        $depDir = null;

        foreach ($roots as $base) {
            if (is_dir($base.'/'.$dep)) {
                $depDir = $base.'/'.$dep;

                break;
            }
        }

        if (null === $depDir) {
            $missing[] = $dep.' (not present)';

            continue;
        }

        $depTables = declaredTables($depDir);

        if (!$depTables) {
            continue;   // nothing to verify against; presence is all there is
        }

        // Every declared table, not merely one of them.
        //
        // Accepting "at least one exists" was the first version and it is too
        // lenient to be useful: ahgCorePlugin declares five tables, and with
        // three of them dropped the dependency still counted as satisfied. A
        // half-installed dependency is precisely the state that produces a
        // missing-table 500 later, so it has to fail here.
        $absent = [];

        foreach ($depTables as $t) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $st->execute([$t]);
            $exists = (int) $st->fetchColumn();
            $st->closeCursor();

            if (0 === $exists) {
                $absent[] = $t;
            }
        }

        if ($absent) {
            $missing[] = sprintf(
                '%s (schema incomplete: %d of %d tables absent, e.g. %s)',
                $dep,
                count($absent),
                count($depTables),
                implode(', ', array_slice($absent, 0, 3))
            );
        }
    }

    return $missing ?: null;
}

$root = dirname(__DIR__);                    // the plugin directory, once shipped
$pluginsDir = dirname($root);                // plugins/
$targets = [];

/**
 * Where plugins live, depending on how this script was reached.
 *
 * Shipped inside a bundle it sits at plugins/<plugin>/bin/, so dirname twice is
 * plugins/ and the original single guess was right. Run from the development
 * checkout at atom-framework/bin/ it is not: dirname twice lands on the AtoM
 * root, and plugins/<name> was never looked for at all.
 *
 * That cost an install on 2026-08-17. `--plugin=ahgSiteRecordPlugin` resolved to
 * <root>/ahgSiteRecordPlugin, which does not exist, and the run printed "Schema
 * loaded." having created nothing - the exact silent half-install this script
 * was written to stop. Both candidate layouts are searched now, and a name that
 * matches neither is a hard error rather than a quiet success.
 */
$candidateRoots = array_values(array_unique(array_filter([
    $pluginsDir,                          // shipped: plugins/<plugin>/bin/
    dirname($root).'/plugins',            // checkout: <atom>/plugins/
    dirname($root).'/atom-ahg-plugins',   // checkout: <atom>/atom-ahg-plugins/
], 'is_dir')));

if (!empty($opt['plugin'])) {
    $found = null;
    foreach ($candidateRoots as $base) {
        if (is_file($base.'/'.$opt['plugin'].'/database/install.sql')) {
            $found = $base.'/'.$opt['plugin'];

            break;
        }
    }

    if (null === $found) {
        fwrite(STDERR, "plugin '{$opt['plugin']}' has no database/install.sql under any of:\n");
        foreach ($candidateRoots as $base) {
            fwrite(STDERR, '  '.$base."/{$opt['plugin']}/database/install.sql\n");
        }
        exit(2);
    }

    $targets[] = $found;
} elseif (is_file($root.'/database/install.sql')) {
    $targets[] = $root;
} else {
    foreach ($candidateRoots as $base) {
        foreach (glob($base.'/*/database/install.sql') as $f) {
            $targets[] = dirname(dirname($f));
        }
    }
}

if (!$targets) {
    fwrite(STDERR, "no plugin with database/install.sql found\n");
    exit(2);
}

/**
 * Connect over a socket when asked, and never hang while trying.
 *
 * `--socket=/path/to/mysqld.sock`, or `--host=` with an empty value, builds a
 * DSN with no host in it, which is how PDO is told to use the unix socket.
 *
 * This is not a niche case. RARI's own AtoM configuration reads
 * `mysql:dbname=atom;port=3306` - no host at all - and on that server a DSN
 * containing host=localhost does not fail, it HANGS. On 2026-08-17 that took
 * two attempts to install a plugin and looked like a broken script rather than
 * a connection problem, because nothing was printed either way.
 *
 * ATTR_TIMEOUT means the worst case is now a message after ten seconds. An
 * installer that stops responding is harder to diagnose than one that says it
 * cannot connect - especially for whoever runs this once, on a production
 * server, having never seen it before.
 */
// --force installs regardless of unmet dependencies. Deliberately explicit:
// the default is to refuse, because a half-installed stack fails later and
// further from the cause.
$force = array_key_exists('force', $opt);

$socket = $opt['socket'] ?? null;
$hostGiven = array_key_exists('host', $opt) && '' !== (string) $opt['host'];

if (null !== $socket && '' !== $socket) {
    $dsn = "mysql:unix_socket={$socket};dbname={$opt['database']};charset=utf8mb4";
} elseif (!$hostGiven && array_key_exists('host', $opt)) {
    // --host= given deliberately empty: let PDO fall back to its socket.
    $dsn = "mysql:dbname={$opt['database']};charset=utf8mb4";
} else {
    $dsn = "mysql:host={$host};dbname={$opt['database']};charset=utf8mb4";
}

try {
    $pdo = new PDO(
        $dsn,
        $opt['user'],
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'cannot connect: '.$e->getMessage()."\n");
    fwrite(STDERR, "dsn: {$dsn}\n");
    fwrite(STDERR, "If the server has no TCP listener, pass --socket=/var/run/mysqld/mysqld.sock or --host= (empty).\n");
    exit(2);
}

$exit = 0;

// Counted so a run that applied nothing cannot end on a success message.
$applied = 0;

foreach ($targets as $dir) {
    $name = basename($dir);
    $file = $dir.'/database/install.sql';

    // Refuse rather than half-install when a declared dependency is absent.
    //
    // Every AHG plugin names what it needs in extension.json. Nothing checked,
    // so installing a plugin whose dependency was missing loaded its schema
    // quite happily and then failed at first use - a missing table, or a
    // missing service class, somewhere far from the install that caused it.
    //
    // Failing here means the operator learns at the moment they can still act
    // on it, with the name of the thing to install first.
    if (!$force && null !== ($unmet = unmetDependencies($dir, $candidateRoots, $pdo))) {
        fwrite(STDERR, "{$name}: not installed - missing dependencies: ".implode(', ', $unmet)."\n");
        fwrite(STDERR, "  install ".implode(' and ', $unmet)." first, or pass --force to override.\n");
        $exit = 2;

        continue;
    }

    if (!is_file($file)) {
        // A plugin with no tables is a normal thing - ahgUiOverridesPlugin and
        // ahgThemeB5Plugin are templates and helpers, and neither owns any
        // schema. Reporting that as a failure made a clean run look broken and
        // trained people to ignore the exit status, which is the opposite of
        // what this script is for.
        //
        // It is only a failure when the plugin CLAIMS tables and ships no SQL
        // to create them.
        $declared = declaredTables($dir);

        if ($declared) {
            fwrite(STDERR, "{$name}: declares ".count($declared)." tables but has no database/install.sql\n");
            $exit = 2;
        } else {
            say("\n{$name}\n  no schema - nothing to install");
        }

        continue;
    }

    ++$applied;

    say("\n{$name}");

    $pending = statements((string) file_get_contents($file));
    $done = $skipped = 0;
    $pass = 0;
    $failures = [];

    // Convergence: keep retrying dependency failures while a pass still helps.
    while ($pending && $pass < 10) {
        ++$pass;
        $stillPending = [];
        $failures = [];

        foreach ($pending as $stmt) {
            if ($dryRun) {
                ++$done;
                continue;
            }

            try {
                // A grouped sequence is run part by part on the one connection,
                // so session state carries across without needing multi-statement
                // support. Any failure aborts the whole group, which is correct:
                // an EXECUTE is meaningless without its PREPARE.
                foreach (explode(";\n", $stmt) as $part) {
                    $part = trim($part);
                    if ('' === $part) {
                        continue;
                    }

                    // prepare/execute/closeCursor, not exec().
                    //
                    // install.sql files test for an existing constraint with
                    // `SET @x = (SELECT ... FROM information_schema...)`. Through
                    // exec() that leaves the result set open, and every statement
                    // afterwards fails with "Cannot execute queries while other
                    // unbuffered queries are active" - which looked like a schema
                    // problem and was not. MYSQL_ATTR_USE_BUFFERED_QUERY is
                    // deprecated and does not fix it; closing the cursor does.
                    $handle = $pdo->prepare($part);
                    $handle->execute();
                    $handle->closeCursor();
                }
                ++$done;
            } catch (PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? 0);

                if (in_array($code, ALREADY_DONE, true)) {
                    ++$skipped;
                    continue;
                }

                if (in_array($code, RETRYABLE, true)) {
                    $stillPending[] = $stmt;
                    $failures[$code] = $e->getMessage();
                    continue;
                }

                $failures[$code] = $e->getMessage();
                $exit = 1;
                say(sprintf('  ERROR %d: %s', $code, preg_replace('/\s+/', ' ', $e->getMessage())));
                say('    in: '.substr(preg_replace('/\s+/', ' ', $stmt), 0, 120));
            }
        }

        // No progress this pass means the remainder cannot succeed.
        if (count($stillPending) === count($pending)) {
            foreach ($failures as $code => $msg) {
                say(sprintf('  UNRESOLVED %d: %s', $code, preg_replace('/\s+/', ' ', $msg)));
                $exit = 1;
            }
            break;
        }

        $pending = $stillPending;
    }

    say(sprintf('  executed %d, already present %d, unresolved %d, passes %d',
        $done, $skipped, count($pending), $pass));

    // The check the old command never made: are the declared tables really there?
    $manifest = $dir.'/extension.json';

    if (!$dryRun && is_file($manifest)) {
        $declared = json_decode((string) file_get_contents($manifest), true)['tables'] ?? [];
        $missing = [];

        foreach ($declared as $table) {
            $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables
                                WHERE table_schema = DATABASE() AND table_name = ?');
            $q->execute([$table]);

            if (!$q->fetchColumn()) {
                $missing[] = $table;
            }
        }

        if ($missing) {
            say(sprintf('  MISSING %d of %d declared tables: %s',
                count($missing), count($declared), implode(', ', $missing)));
            $exit = 1;
        } elseif ($declared) {
            say(sprintf('  verified all %d declared tables exist', count($declared)));
        }
    }
}

if (0 === $applied) {
    // "Schema loaded" after loading nothing is the worst outcome this script
    // can produce: the operator moves on believing the install happened.
    fwrite(STDERR, "\nNothing was applied - no schema file was read.\n");
    exit(2);
}

say($exit === 0
    ? sprintf("\nSchema loaded (%d plugin%s).", $applied, 1 === $applied ? '' : 's')
    : "\nSchema INCOMPLETE - see the errors above.");

exit($exit);
