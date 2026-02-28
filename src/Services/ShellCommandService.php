<?php

namespace AtomFramework\Services;

/**
 * Shell Command Service — safe shell execution utilities.
 *
 * Provides properly escaped shell command builders to prevent
 * command injection vulnerabilities. All external input passed
 * to shell commands MUST be routed through this service.
 */
class ShellCommandService
{
    /**
     * Allowed service names for systemctl operations.
     */
    private const ALLOWED_SERVICES = [
        'nginx',
        'php8.3-fpm',
        'php8.2-fpm',
        'php8.1-fpm',
        'php8.0-fpm',
        'mysql',
        'mariadb',
        'elasticsearch',
        'memcached',
        'redis',
        'clamav-daemon',
        'fuseki',
    ];

    /**
     * Build a mysqldump command with properly escaped credentials.
     *
     * @param string     $host     Database host
     * @param int|string $port     Database port
     * @param string     $user     Database username
     * @param string     $password Database password (empty string = no password arg)
     * @param string     $database Database name
     * @param string     $outputFile Path to write the SQL dump
     * @param string     $errorFile  Path for stderr output
     * @param array      $extraArgs  Additional mysqldump flags
     */
    public static function buildMysqldumpCommand(
        string $host,
        int|string $port,
        string $user,
        string $password,
        string $database,
        string $outputFile,
        string $errorFile = '/dev/null',
        array $extraArgs = []
    ): string {
        $parts = [
            'mysqldump',
            '-h', escapeshellarg($host),
            '-P', escapeshellarg((string) $port),
            '-u', escapeshellarg($user),
        ];

        if ($password !== '') {
            $parts[] = '-p' . escapeshellarg($password);
        }

        // Default safe flags
        $defaults = [
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--opt',
            '--quick',
            '--max_allowed_packet=512M',
        ];

        foreach ($defaults as $flag) {
            $parts[] = $flag;
        }

        foreach ($extraArgs as $arg) {
            // Only allow flags starting with --
            if (str_starts_with($arg, '--')) {
                $parts[] = $arg;
            }
        }

        $parts[] = escapeshellarg($database);

        return implode(' ', $parts)
            . ' > ' . escapeshellarg($outputFile)
            . ' 2>' . escapeshellarg($errorFile);
    }

    /**
     * Build a mysql restore command with properly escaped credentials.
     */
    public static function buildMysqlRestoreCommand(
        string $host,
        int|string $port,
        string $user,
        string $password,
        string $database,
        string $inputFile,
        bool $isGzipped = false
    ): string {
        $mysqlParts = [
            'mysql',
            '-h', escapeshellarg($host),
            '-P', escapeshellarg((string) $port),
            '-u', escapeshellarg($user),
        ];

        if ($password !== '') {
            $mysqlParts[] = '-p' . escapeshellarg($password);
        }

        $mysqlParts[] = escapeshellarg($database);
        $mysqlCmd = implode(' ', $mysqlParts);

        if ($isGzipped) {
            return 'gunzip -c ' . escapeshellarg($inputFile) . ' | ' . $mysqlCmd . ' 2>&1';
        }

        return $mysqlCmd . ' < ' . escapeshellarg($inputFile) . ' 2>&1';
    }

    /**
     * Execute a command in a specific directory after validating the path.
     *
     * @param string $directory The directory to cd into
     * @param string $command   The command to run (should already be escaped)
     * @param array  $output    Captured output lines
     * @param int    $returnCode Exit code
     * @return bool True if command succeeded (exit code 0)
     */
    public static function execInDir(string $directory, string $command, array &$output = [], int &$returnCode = 0): bool
    {
        $realDir = realpath($directory);
        if ($realDir === false || !is_dir($realDir)) {
            $returnCode = 1;
            $output = ['Invalid directory: ' . $directory];
            return false;
        }

        $fullCommand = 'cd ' . escapeshellarg($realDir) . ' && ' . $command;
        exec($fullCommand, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Validate a service name against the allowlist.
     */
    public static function isAllowedService(string $name): bool
    {
        return in_array($name, self::ALLOWED_SERVICES, true);
    }

    /**
     * Build a systemctl command for an allowed service.
     *
     * @param string $action  The systemctl action (restart, start, stop, status)
     * @param string $service The service name (must be in allowlist)
     * @return string|null The command string, or null if service not allowed
     */
    public static function buildSystemctlCommand(string $action, string $service): ?string
    {
        $allowedActions = ['restart', 'start', 'stop', 'status', 'reload'];
        if (!in_array($action, $allowedActions, true)) {
            return null;
        }

        if (!self::isAllowedService($service)) {
            return null;
        }

        return 'systemctl ' . escapeshellarg($action) . ' ' . escapeshellarg($service) . ' 2>/dev/null';
    }

    /**
     * Escape a PostScript string to prevent PS injection.
     *
     * Strips characters that could be used for PostScript injection:
     * parentheses, backslashes, and other control sequences.
     */
    public static function escapePostScript(string $input): string
    {
        // Remove PS-significant characters: ( ) \ and non-printable control chars
        $cleaned = preg_replace('/[()\\\\]/', '', $input);
        // Remove any remaining non-printable characters except space/newline
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned);

        return $cleaned;
    }

    /**
     * Validate that a command name contains no shell metacharacters.
     *
     * Only allows alphanumeric characters, hyphens, underscores, dots, and forward slashes.
     *
     * @param string $command The command name to validate
     * @return bool True if the command name is safe
     */
    public static function validateCommand(string $command): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $command);
    }

    /**
     * Build a tar archive command with escaped paths.
     */
    public static function buildTarCommand(string $tarFile, string $sourceDir, array $excludes = [], array $includes = []): string
    {
        $parts = ['tar', '-czf', escapeshellarg($tarFile), '-C', escapeshellarg($sourceDir)];

        foreach ($excludes as $exclude) {
            $parts[] = '--exclude=' . escapeshellarg($exclude);
        }

        $parts[] = '--warning=no-file-changed';

        if (!empty($includes)) {
            foreach ($includes as $include) {
                $parts[] = escapeshellarg($include);
            }
        } else {
            $parts[] = '.';
        }

        return implode(' ', $parts) . ' 2>/dev/null || true';
    }

    /**
     * Build a zip command with escaped paths.
     */
    public static function buildZipCommand(string $sourceDir, string $zipFile, array $excludePatterns = []): string
    {
        $parts = [
            'cd', escapeshellarg($sourceDir), '&&',
            'zip', '-r', escapeshellarg($zipFile), '.',
        ];

        foreach ($excludePatterns as $pattern) {
            $parts[] = '-x';
            $parts[] = escapeshellarg($pattern);
        }

        return implode(' ', $parts) . ' 2>/dev/null';
    }
}
