<?php

namespace AtomFramework\Extensions\Handlers;

use Illuminate\Database\Capsule\Manager as DB;

class ExtensionDataHandler
{
    protected string $backupPath;

    public function __construct()
    {
        $this->backupPath = '/usr/share/nginx/archive/data/backups/extensions';
        
        if (!is_dir($this->backupPath)) {
            @mkdir($this->backupPath, 0755, true);
        }
    }

    public function backup(string $machineName, object $extension): string
    {
        $tables = json_decode($extension->tables_created ?? '[]', true);
        
        if (empty($tables)) {
            return '';
        }

        $timestamp = date('Y-m-d_His');
        $backupDir = $this->backupPath . '/' . $machineName;
        $backupFile = $backupDir . "/{$machineName}_{$timestamp}.sql";

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $sql = "-- Extension Backup: {$machineName}\n";
        $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Version: {$extension->version}\n\n";

        foreach ($tables as $table) {
            $sql .= $this->exportTable($table);
        }

        file_put_contents($backupFile, $sql);

        return $backupFile;
    }

    protected function exportTable(string $table): string
    {
        $sql = "-- Table: {$table}\n";

        try {
            $exists = DB::select("SHOW TABLES LIKE ?", [$table]);
            if (empty($exists)) {
                return "-- Table {$table} does not exist\n\n";
            }
        } catch (\Exception $e) {
            return "-- Error checking table {$table}: {$e->getMessage()}\n\n";
        }

        try {
            $create = DB::select("SHOW CREATE TABLE `{$table}`");
            if (!empty($create)) {
                $createSql = $create[0]->{'Create Table'} ?? '';
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $createSql . ";\n\n";
            }
        } catch (\Exception $e) {
            $sql .= "-- Error getting create statement: {$e->getMessage()}\n";
        }

        try {
            $rows = DB::table($table)->get();
            
            if ($rows->count() > 0) {
                $columns = array_keys((array)$rows->first());
                $columnList = '`' . implode('`, `', $columns) . '`';

                foreach ($rows as $row) {
                    $values = array_map(function($v) {
                        if ($v === null) return 'NULL';
                        return "'" . addslashes($v) . "'";
                    }, (array)$row);
                    
                    $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        } catch (\Exception $e) {
            $sql .= "-- Error exporting data: {$e->getMessage()}\n\n";
        }

        return $sql;
    }

    public function restoreFromBackup(string $backupFile): bool
    {
        if (!file_exists($backupFile)) {
            throw new \RuntimeException("Backup file not found: {$backupFile}");
        }

        $sql = file_get_contents($backupFile);
        DB::unprepared($sql);

        return true;
    }

    public function getTableRecordCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function dropTable(string $table): bool
    {
        try {
            DB::statement("DROP TABLE IF EXISTS `{$table}`");
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to drop table {$table}: " . $e->getMessage());
        }
    }

    public function getBackupPath(string $machineName): string
    {
        return $this->backupPath . '/' . $machineName;
    }

    public function listBackups(string $machineName): array
    {
        $dir = $this->backupPath . '/' . $machineName;
        
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.sql');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'file' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'date' => filemtime($file),
            ];
        }

        usort($backups, fn($a, $b) => $b['date'] - $a['date']);

        return $backups;
    }

    public function cleanupBackups(string $machineName, int $keep = 5): int
    {
        $backups = $this->listBackups($machineName);
        $deleted = 0;

        if (count($backups) <= $keep) {
            return 0;
        }

        $toDelete = array_slice($backups, $keep);

        foreach ($toDelete as $backup) {
            if (unlink($backup['path'])) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
