<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * The one way to write ahg_error_log.
 *
 * Five places wrote this table directly, each with its own column set and its
 * own idea of what to record: ErrorNotificationService, CsrfService, and the
 * SharePoint, metadata-export and AI ingest services. None of them collapsed
 * repeats, so a fault hit repeatedly wrote a row per event - one scanner
 * sweeping unroutable paths put 31 identical rows in the log in two and a half
 * minutes, an absent table queried on every record view put 21 more, and six
 * token-less POSTs put six. In a log a person reads to find out what is wrong,
 * that is the noise burying the signal.
 *
 * Five implementations is also how it stayed unnoticed: a note in our own
 * records said there were TWO writers, and a fix applied to one of them looked
 * complete and changed nothing for the others. Routing every writer through
 * here means the behaviour is defined once, and the next service that logs an
 * error inherits it rather than inventing a sixth variant.
 *
 * Repeats are COUNTED, not dropped. The 21 rows above were how the scale of
 * that fault became visible; discarding them would have hidden it. One row
 * reading 21x carries the same information at one row's cost.
 *
 * Never throws. Logging is the thing you do when something has already gone
 * wrong, so it must not be able to make it worse - every caller here is either
 * inside an exception handler or on a request-handling path.
 */
class ErrorLogWriter
{
    /**
     * Seconds within which the same signature is treated as a repeat rather
     * than a new event. Matches ErrorNotificationService's email throttle, so
     * both sides agree on what "the same error again" means.
     */
    private static int $window = 300;

    /**
     * Whether the schema carries signature/occurrences/last_seen_at. Resolved
     * once per request; false on an instance that has not run the schema
     * upgrade, which then keeps the previous row-per-event behaviour.
     */
    private static ?bool $canDedupe = null;

    /**
     * Record one error.
     *
     * @param array $row      column => value, as the callers already build it.
     *                        Anything absent is simply not written.
     * @param int|null $window override the repeat window in seconds
     */
    public static function record(array $row, ?int $window = null): void
    {
        try {
            if (!self::tableExists()) {
                return;
            }

            $row['created_at'] = $row['created_at'] ?? date('Y-m-d H:i:s');

            if (!self::canDedupe()) {
                DB::table('ahg_error_log')->insert($row);

                return;
            }

            $signature = $row['signature'] ?? self::signature(
                (string) ($row['file'] ?? ''),
                (int) ($row['line'] ?? 0),
                (string) ($row['message'] ?? '')
            );

            $seconds = $window ?? self::$window;

            // NOW() and INTERVAL are evaluated by MySQL rather than compared
            // against a PHP timestamp on purpose: timestamps on these instances
            // are written in the application timezone while the database clock
            // is UTC, and mixing the two silently misses by hours.
            $recent = DB::table('ahg_error_log')
                ->where('signature', $signature)
                ->whereRaw('COALESCE(last_seen_at, created_at) > NOW() - INTERVAL ? SECOND', [$seconds])
                ->orderByDesc('id')
                ->first();

            if (null !== $recent) {
                DB::table('ahg_error_log')
                    ->where('id', $recent->id)
                    ->update([
                        'occurrences' => DB::raw('occurrences + 1'),
                        'last_seen_at' => DB::raw('NOW()'),
                    ]);

                return;
            }

            $row['signature'] = $signature;
            $row['occurrences'] = 1;

            DB::table('ahg_error_log')->insert($row);
        } catch (\Throwable $e) {
            // Deliberately silent. error_log() here would be the same noise in
            // a different file, and the caller is already handling a failure.
        }
    }

    /**
     * The key that decides whether two errors are the same error.
     *
     * file + line + the first 200 characters of the message: the same trio the
     * email throttle uses. The message is truncated because several carry a
     * record id or a path that differs per request while the fault does not.
     */
    public static function signature(string $file, int $line, string $message): string
    {
        return md5($file . ':' . $line . ':' . mb_substr($message, 0, 200));
    }

    private static function tableExists(): bool
    {
        static $exists = null;

        if (null === $exists) {
            try {
                $exists = DB::schema()->hasTable('ahg_error_log');
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }

    private static function canDedupe(): bool
    {
        if (null === self::$canDedupe) {
            try {
                $schema = DB::schema();
                self::$canDedupe = $schema->hasColumn('ahg_error_log', 'signature')
                    && $schema->hasColumn('ahg_error_log', 'occurrences')
                    && $schema->hasColumn('ahg_error_log', 'last_seen_at');
            } catch (\Throwable $e) {
                self::$canDedupe = false;
            }
        }

        return self::$canDedupe;
    }
}
