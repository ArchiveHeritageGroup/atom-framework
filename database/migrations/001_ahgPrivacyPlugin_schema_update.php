<?php
/**
 * Add missing columns to privacy tables
 */
use Illuminate\Database\Capsule\Manager as DB;

return new class {
    public function up(): void
    {
        // privacy_processing_activity columns
        $processingColumns = [
            'jurisdiction' => "VARCHAR(30) NOT NULL DEFAULT 'popia' AFTER id",
            'description' => "TEXT NULL AFTER name",
            'third_countries' => "JSON NULL AFTER recipients",
            'dpia_date' => "DATE NULL AFTER dpia_completed",
            'owner' => "VARCHAR(255) NULL AFTER status",
            'next_review_date' => "DATE NULL AFTER owner",
            'lawful_basis_code' => "VARCHAR(50) NULL AFTER lawful_basis"
        ];

        foreach ($processingColumns as $column => $definition) {
            $this->addColumnIfNotExists('privacy_processing_activity', $column, $definition);
        }

        // privacy_consent_record columns
        $consentColumns = [
            'status' => "VARCHAR(50) NULL DEFAULT 'active'",
            'withdrawn_date' => "DATE NULL"
        ];

        foreach ($consentColumns as $column => $definition) {
            $this->addColumnIfNotExists('privacy_consent_record', $column, $definition);
        }
    }

    protected function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            try {
                DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                echo "  Added {$table}.{$column}\n";
            } catch (\Exception $e) {
                echo "  {$table}.{$column}: " . $e->getMessage() . "\n";
            }
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND column_name = ?
        ", [$table, $column]);
        
        return $result[0]->cnt > 0;
    }
};
