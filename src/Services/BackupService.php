<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class BackupService
{
    private BackupSettingsService $settings;

    public function __construct()
    {
        $this->settings = new BackupSettingsService();
        $this->ensureDirectories();
    }

    private function ensureDirectories(): void
    {
        $backupPath = $this->settings->get('backup_path', '/var/backups/atom');
        $logPath = dirname($this->settings->get('log_path', '/var/log/atom/backup.log'));
        
        foreach ([$backupPath, $logPath] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function log(string $message): void
    {
        $logPath = $this->settings->get('log_path', '/var/log/atom/backup.log');
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        file_put_contents($logPath, $line, FILE_APPEND);
    }

    public function listBackups(): array
    {
        $backupPath = $this->settings->get('backup_path', '/var/backups/atom');
        $backups = [];
        
        if (!is_dir($backupPath)) {
            return $backups;
        }

        $dirs = glob($backupPath . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $backupId = basename($dir);
            $manifestFile = $dir . '/manifest.json';
            
            $backup = [
                'id' => $backupId,
                'path' => $dir,
                'created_at' => date('Y-m-d H:i:s', filectime($dir)),
                'size' => $this->getDirectorySize($dir),
                'components' => [],
            ];
            
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);
                $backup = array_merge($backup, $manifest);
            }
            
            if (is_dir($dir . '/database') || !empty(glob($dir . '/database/*.sql.gz'))) {
                $backup['components']['database'] = true;
            }
            if (file_exists($dir . '/uploads.tar.gz')) {
                $backup['components']['uploads'] = true;
            }
            if (file_exists($dir . '/plugins.tar.gz')) {
                $backup['components']['plugins'] = true;
            }
            if (file_exists($dir . '/framework.tar.gz')) {
                $backup['components']['framework'] = true;
            }
            if (is_dir($dir . '/fuseki')) {
                $backup['components']['fuseki'] = true;
            }
            
            $backups[] = $backup;
        }

        usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        
        return $backups;
    }

    public function createBackup(array $options = []): array
    {
        $backupPath = $this->settings->get('backup_path', '/var/backups/atom');
        $backupId = date('Y-m-d_H-i-s') . '_' . substr(md5(uniqid()), 0, 8);
        $backupDir = $backupPath . '/' . $backupId;

        mkdir($backupDir, 0755, true);
        $this->log("Starting backup: {$backupId}");

        $result = [
            'id' => $backupId,
            'path' => $backupDir,
            'started_at' => date('Y-m-d H:i:s'),
            'components' => [],
            'status' => 'in_progress',
        ];

        try {
            DB::table('backup_history')->insert([
                'backup_id' => $backupId,
                'backup_path' => $backupDir,
                'backup_type' => $options['type'] ?? 'manual',
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Continue without history
        }

        try {
            // Database
            if ($options['database'] ?? $this->settings->get('include_database', true)) {
                $result['components']['database'] = $this->backupDatabase($backupDir);
            }
            
            // Uploads
            if ($options['uploads'] ?? $this->settings->get('include_uploads', true)) {
                $result['components']['uploads'] = $this->backupUploads($backupDir);
            }
            
            // Plugins
            if ($options['plugins'] ?? $this->settings->get('include_plugins', true)) {
                $result['components']['plugins'] = $this->backupPlugins($backupDir);
            }
            
            // Framework
            if ($options['framework'] ?? $this->settings->get('include_framework', true)) {
                $result['components']['framework'] = $this->backupFramework($backupDir);
            }
            
            // Fuseki / RIC Triplestore
            if ($options['fuseki'] ?? $this->settings->get('include_fuseki', true)) {
                $result['components']['fuseki'] = $this->backupFuseki($backupDir);
            }

            $result['status'] = 'completed';
            $result['completed_at'] = date('Y-m-d H:i:s');
            $result['size'] = $this->getDirectorySize($backupDir);

            file_put_contents($backupDir . '/manifest.json', json_encode($result, JSON_PRETTY_PRINT));
            $this->log("Backup completed: {$backupId}");

            try {
                DB::table('backup_history')->where('backup_id', $backupId)->update([
                    'status' => 'completed',
                    'size_bytes' => $result['size'],
                    'components' => json_encode($result['components']),
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                // Continue
            }

            $this->cleanupOldBackups();

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
            $this->log("Backup failed: {$backupId} - " . $e->getMessage());

            try {
                DB::table('backup_history')->where('backup_id', $backupId)->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $ex) {
                // Continue
            }
        }

        return $result;
    }

    private function backupDatabase(string $backupDir): array
    {
        $dbDir = $backupDir . '/database';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $host = $this->settings->get('db_host', 'localhost');
        $database = $this->settings->get('db_name', 'archive');
        $username = $this->settings->get('db_user', 'root');
        $password = $this->settings->get('db_password', '');
        $port = $this->settings->get('db_port', 3306);
        $compression = $this->settings->get('compression_level', 6);

        $sqlFile = $dbDir . '/' . $database . '.sql';
        $errorFile = $dbDir . '/mysqldump_errors.log';
        
        $passwordArg = '';
        if (!empty($password)) {
            $passwordArg = "-p'" . addslashes($password) . "'";
        }
        
        $cmd = sprintf(
            "mysqldump -h '%s' -P '%s' -u '%s' %s --single-transaction --routines --triggers --events --opt --quick --max_allowed_packet=512M '%s' > '%s' 2>'%s'",
            addslashes($host),
            addslashes($port),
            addslashes($username),
            $passwordArg,
            addslashes($database),
            $sqlFile,
            $errorFile
        );

        $this->log("Starting mysqldump for database: {$database}");
        
        exec($cmd, $output, $returnCode);

        $errors = '';
        if (file_exists($errorFile)) {
            $errors = trim(file_get_contents($errorFile));
            if (empty($errors) || strpos($errors, 'Warning') !== false) {
                @unlink($errorFile);
            }
        }

        if (!file_exists($sqlFile)) {
            throw new \Exception("Database backup failed: SQL file not created. Error: " . $errors);
        }

        $sqlSize = filesize($sqlFile);
        
        if ($sqlSize < 1000) {
            $content = file_get_contents($sqlFile);
            throw new \Exception("Database backup incomplete. Size: {$sqlSize} bytes");
        }

        $this->log("mysqldump completed. SQL file size: " . round($sqlSize / 1024 / 1024, 2) . " MB");

        exec("gzip -{$compression} '{$sqlFile}'");

        if (!file_exists($sqlFile . '.gz')) {
            throw new \Exception("Failed to compress SQL file");
        }

        $gzSize = filesize($sqlFile . '.gz');
        $this->log("Compressed to: " . round($gzSize / 1024 / 1024, 2) . " MB");

        return [
            'status' => 'success',
            'file' => $sqlFile . '.gz',
            'size' => $gzSize,
            'uncompressed_size' => $sqlSize,
        ];
    }

    /**
     * Backup Fuseki triplestore (RIC data)
     */
    private function backupFuseki(string $backupDir): array
    {
        $fusekiUrl = $this->settings->get('fuseki_url', 'http://localhost:3030');
        $dataset = $this->settings->get('fuseki_dataset', 'ric');
        $dataPath = $this->settings->get('fuseki_data_path', '/var/lib/fuseki/databases');
        
        $fusekiDir = $backupDir . '/fuseki';
        if (!is_dir($fusekiDir)) {
            mkdir($fusekiDir, 0755, true);
        }

        $this->log("Starting Fuseki backup for dataset: {$dataset}");

        // Method 1: Try HTTP export via Graph Store Protocol
        $exportFile = $fusekiDir . '/' . $dataset . '.nq';
        $exportUrl = rtrim($fusekiUrl, '/') . '/' . $dataset . '/data';
        
        $curlCmd = sprintf(
            "curl -s -f -H 'Accept: application/n-quads' '%s' > '%s' 2>/dev/null",
            $exportUrl,
            $exportFile
        );
        
        exec($curlCmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($exportFile) && filesize($exportFile) > 0) {
            $size = filesize($exportFile);
            $this->log("Fuseki HTTP export successful: " . round($size / 1024, 2) . " KB");
            
            exec("gzip -6 '{$exportFile}'");
            
            return [
                'status' => 'success',
                'method' => 'http_export',
                'file' => $exportFile . '.gz',
                'size' => file_exists($exportFile . '.gz') ? filesize($exportFile . '.gz') : 0,
                'format' => 'n-quads',
            ];
        }

        $this->log("HTTP export failed (code {$returnCode}), trying file backup...");
        @unlink($exportFile);

        // Method 2: File copy of TDB2 data
        $datasetPath = $dataPath . '/' . $dataset;
        
        if (!is_dir($datasetPath)) {
            $altPaths = [
                '/opt/fuseki/run/databases/' . $dataset,
                '/var/fuseki/databases/' . $dataset,
                $dataPath . '/Data-' . $dataset,
                '/usr/share/fuseki/run/databases/' . $dataset,
            ];
            
            foreach ($altPaths as $altPath) {
                if (is_dir($altPath)) {
                    $datasetPath = $altPath;
                    $this->log("Found Fuseki data at: {$altPath}");
                    break;
                }
            }
        }

        if (!is_dir($datasetPath)) {
            $this->log("Fuseki dataset directory not found");
            return [
                'status' => 'skipped',
                'reason' => "Fuseki dataset not found. Tried: {$datasetPath}",
            ];
        }

        $tarFile = $fusekiDir . '/' . $dataset . '_tdb2.tar.gz';
        $cmd = sprintf(
            "tar -czf '%s' -C '%s' . 2>/dev/null",
            $tarFile,
            $datasetPath
        );
        
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($tarFile)) {
            $size = filesize($tarFile);
            $this->log("Fuseki file backup successful: " . round($size / 1024 / 1024, 2) . " MB");
            
            return [
                'status' => 'success',
                'method' => 'file_copy',
                'file' => $tarFile,
                'size' => $size,
            ];
        }

        return [
            'status' => 'failed',
            'reason' => 'Both HTTP export and file copy failed',
        ];
    }

    private function backupUploads(string $backupDir): array
    {
        $atomRoot = $this->settings->getAtomRoot();
        $uploadsDir = $atomRoot . '/uploads';
        
        if (!is_dir($uploadsDir)) {
            return ['status' => 'skipped', 'reason' => 'uploads directory not found'];
        }

        $tarFile = $backupDir . '/uploads.tar.gz';
        
        $cmd = sprintf(
            "tar -czf '%s' -C '%s' . --exclude='backups' --exclude='backups/*' --warning=no-file-changed 2>/dev/null || true",
            $tarFile,
            $uploadsDir
        );
        exec($cmd, $output, $returnCode);

        return [
            'status' => file_exists($tarFile) ? 'success' : 'failed',
            'file' => $tarFile,
            'size' => file_exists($tarFile) ? filesize($tarFile) : 0,
        ];
    }

    private function backupPlugins(string $backupDir): array
    {
        $atomRoot = $this->settings->getAtomRoot();
        $pluginsDir = $atomRoot . '/plugins';
        $tarFile = $backupDir . '/plugins.tar.gz';

        $customPlugins = $this->settings->get('custom_plugins', []);
        if (is_string($customPlugins)) {
            $customPlugins = json_decode($customPlugins, true) ?? [];
        }

        $includeArgs = '';
        foreach ($customPlugins as $plugin) {
            if (is_dir($pluginsDir . '/' . $plugin)) {
                $includeArgs .= " '" . addslashes($plugin) . "'";
            }
        }

        if (empty(trim($includeArgs))) {
            return ['status' => 'skipped', 'reason' => 'no custom plugins found'];
        }

        $cmd = "tar -czf '{$tarFile}' -C '{$pluginsDir}' {$includeArgs} 2>/dev/null";
        exec($cmd, $output, $returnCode);

        return [
            'status' => $returnCode === 0 ? 'success' : 'failed',
            'file' => $tarFile,
            'size' => file_exists($tarFile) ? filesize($tarFile) : 0,
        ];
    }

    private function backupFramework(string $backupDir): array
    {
        $atomRoot = $this->settings->getAtomRoot();
        $frameworkDir = $atomRoot . '/atom-framework';
        $tarFile = $backupDir . '/framework.tar.gz';

        if (!is_dir($frameworkDir)) {
            return ['status' => 'skipped', 'reason' => 'framework directory not found'];
        }

        $cmd = "tar -czf '{$tarFile}' -C '{$frameworkDir}' --exclude=vendor . 2>/dev/null";
        exec($cmd, $output, $returnCode);

        return [
            'status' => $returnCode === 0 ? 'success' : 'failed',
            'file' => $tarFile,
            'size' => file_exists($tarFile) ? filesize($tarFile) : 0,
        ];
    }

    private function cleanupOldBackups(): void
    {
        $backupPath = $this->settings->get('backup_path', '/var/backups/atom');
        $maxBackups = $this->settings->get('max_backups', 30);
        $retentionDays = $this->settings->get('retention_days', 90);

        $backups = $this->listBackups();
        
        if (count($backups) > $maxBackups) {
            $toRemove = array_slice($backups, $maxBackups);
            foreach ($toRemove as $backup) {
                $this->deleteBackup($backup['id']);
            }
        }

        $cutoffDate = strtotime("-{$retentionDays} days");
        foreach ($this->listBackups() as $backup) {
            if (strtotime($backup['created_at']) < $cutoffDate) {
                $this->deleteBackup($backup['id']);
            }
        }
    }

    public function deleteBackup(string $backupId): bool
    {
        $backupPath = $this->settings->get('backup_path', '/var/backups/atom');
        $backupDir = $backupPath . '/' . $backupId;
        
        if (!is_dir($backupDir) || strpos($backupId, '..') !== false) {
            return false;
        }

        $this->log("Deleting backup: {$backupId}");
        exec("rm -rf '" . addslashes($backupDir) . "'", $output, $returnCode);
        
        try {
            DB::table('backup_history')->where('backup_id', $backupId)->delete();
        } catch (\Exception $e) {
            // Continue
        }
        
        return $returnCode === 0;
    }

    public function restoreBackup(string $backupId, array $options = []): bool
    {
        $backupPath = $this->settings->get('backup_path', '/var/backups/atom');
        $backupDir = $backupPath . '/' . $backupId;
        
        if (!is_dir($backupDir)) {
            throw new \Exception("Backup not found: {$backupId}");
        }

        $this->log("Starting restore: {$backupId}");
        
        if ($options['restore_database'] ?? true) {
            $dbFiles = glob($backupDir . '/database/*.sql.gz');
            if (!empty($dbFiles)) {
                $this->restoreDatabase($dbFiles[0]);
            }
        }

        if ($options['restore_fuseki'] ?? false) {
            $this->restoreFuseki($backupDir);
        }

        $this->log("Restore completed: {$backupId}");
        return true;
    }

    private function restoreDatabase(string $sqlGzFile): void
    {
        $host = $this->settings->get('db_host', 'localhost');
        $database = $this->settings->get('db_name', 'archive');
        $username = $this->settings->get('db_user', 'root');
        $password = $this->settings->get('db_password', '');
        $port = $this->settings->get('db_port', 3306);

        $passwordArg = '';
        if (!empty($password)) {
            $passwordArg = "-p'" . addslashes($password) . "'";
        }

        $cmd = sprintf(
            "gunzip -c '%s' | mysql -h '%s' -P '%s' -u '%s' %s '%s' 2>&1",
            $sqlGzFile,
            addslashes($host),
            addslashes($port),
            addslashes($username),
            $passwordArg,
            addslashes($database)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Database restore failed: " . implode("\n", $output));
        }
    }

    private function restoreFuseki(string $backupDir): void
    {
        $fusekiUrl = $this->settings->get('fuseki_url', 'http://localhost:3030');
        $dataset = $this->settings->get('fuseki_dataset', 'ric');
        
        $fusekiDir = $backupDir . '/fuseki';
        
        // Check for N-Quads export
        $nqFile = $fusekiDir . '/' . $dataset . '.nq.gz';
        if (file_exists($nqFile)) {
            $this->log("Restoring Fuseki from N-Quads export...");
            
            exec("gunzip -k '{$nqFile}'");
            $unzippedFile = str_replace('.gz', '', $nqFile);
            
            // Clear existing data
            $clearUrl = rtrim($fusekiUrl, '/') . '/' . $dataset . '/update';
            exec("curl -s -X POST -d 'update=CLEAR ALL' '{$clearUrl}'");
            
            // Upload data
            $uploadUrl = rtrim($fusekiUrl, '/') . '/' . $dataset . '/data';
            $cmd = sprintf(
                "curl -s -X POST -H 'Content-Type: application/n-quads' --data-binary '@%s' '%s'",
                $unzippedFile,
                $uploadUrl
            );
            exec($cmd, $output, $returnCode);
            
            @unlink($unzippedFile);
            
            if ($returnCode === 0) {
                $this->log("Fuseki restore completed via HTTP");
                return;
            }
        }

        $tarFile = $fusekiDir . '/' . $dataset . '_tdb2.tar.gz';
        if (file_exists($tarFile)) {
            $this->log("Fuseki TDB2 backup found - manual restore required");
        }
    }

    public function getBackupDetails(string $backupId): ?array
    {
        $backupPath = $this->settings->get('backup_path', '/var/backups/atom');
        $manifestFile = $backupPath . '/' . $backupId . '/manifest.json';
        
        if (!file_exists($manifestFile)) {
            return null;
        }
        
        return json_decode(file_get_contents($manifestFile), true);
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // Return 0 on error
        }
        return $size;
    }

    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getSettings(): BackupSettingsService
    {
        return $this->settings;
    }
}
