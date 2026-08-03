<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

/**
 * Constrain server-side file paths taken from request parameters (#246).
 *
 * Several endpoints accept a filesystem path and then read it: ingest's
 * server-directory and watched-folder options, and preservation's backup
 * verification. Each was gated to administrators but not scoped, so any path on
 * the host was reachable - /etc, /root, another tenant's data. Administrator is
 * not the same as "may read any file on the server", and on a multi-tenant or
 * hosted deployment the distinction matters.
 *
 * `realpath()` does the real work: it resolves `..`, collapses `.`, follows
 * symlinks and returns false for anything that does not exist, so a traversal
 * attempt is normalised before it is compared. Rejecting the literal string
 * `..` is not sufficient - encoded forms and symlinks get past that.
 *
 * Fails closed: anything unresolvable, or outside every configured root,
 * returns null.
 */
class PathGuard
{
    /**
     * Resolve a path and return it only if it lies within one of $roots.
     *
     * @param string $path  untrusted path from a request
     * @param array  $roots absolute directory paths; a match on any is enough
     *
     * @return null|string the resolved real path, or null if it is outside
     *                     every root, does not exist, or cannot be resolved
     */
    public static function within(string $path, array $roots): ?string
    {
        $path = trim($path);
        if ('' === $path) {
            return null;
        }

        $real = realpath($path);
        if (false === $real) {
            return null;
        }

        foreach ($roots as $root) {
            $realRoot = realpath((string) $root);
            if (false === $realRoot) {
                continue;
            }

            // Compare on a separator boundary, otherwise a root of /mnt/nas
            // would also accept /mnt/nas-somewhere-else.
            if ($real === $realRoot
                || 0 === strpos($real, rtrim($realRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * As within(), but for a destination that may not exist yet.
     *
     * A move target such as an ingest "processed" folder is often created by the
     * worker on first use, so realpath() would reject it outright. Validate the
     * parent instead: if the parent resolves inside a root, the leaf is safe to
     * create there. The leaf is passed through basename() so a value like
     * `../../etc/x` cannot climb out via the leaf component.
     *
     * @return null|string the intended absolute path, or null if the parent is
     *                     outside every root
     */
    public static function withinForWrite(string $path, array $roots): ?string
    {
        $path = trim($path);
        if ('' === $path) {
            return null;
        }

        // Already exists - normal rules apply.
        $existing = self::within($path, $roots);
        if (null !== $existing) {
            return $existing;
        }

        $parent = self::within(dirname($path), $roots);
        if (null === $parent) {
            return null;
        }

        return $parent.DIRECTORY_SEPARATOR.basename($path);
    }

    /**
     * Roots that server-side file operations may read from.
     *
     * Defaults to AtoM's upload directory and the digital-object store. An
     * instance that drops deposits elsewhere can extend this without a code
     * change by setting `file_access_roots` in ahg_settings - one absolute path
     * per line, or comma separated.
     */
    public static function defaultRoots(): array
    {
        $roots = [];

        $uploadDir = \sfConfig::get('sf_upload_dir');
        if ($uploadDir) {
            $roots[] = $uploadDir;
        }

        // uploads/r is normally a symlink to the digital-object store (a NAS
        // mount here). realpath() resolves it, so the target must be listed for
        // a path under it to match.
        $objectStore = realpath(($uploadDir ?: '').'/r');
        if (false !== $objectStore) {
            $roots[] = $objectStore;
        }

        try {
            $configured = AhgSettingsService::get('file_access_roots', '');
            foreach (preg_split('/[\r\n,]+/', (string) $configured) as $extra) {
                $extra = trim($extra);
                if ('' !== $extra) {
                    $roots[] = $extra;
                }
            }
        } catch (\Throwable $e) {
            // Settings unavailable - fall back to the built-in roots rather
            // than widening or failing the request.
        }

        return array_values(array_unique(array_filter($roots)));
    }

    /**
     * Human-readable root list, for telling an operator why a path was refused.
     */
    public static function describeRoots(array $roots): string
    {
        return empty($roots) ? '(none configured)' : implode(', ', $roots);
    }
}
