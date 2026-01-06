<?php
/**
 * Add missing columns to privacy tables
 */
use Illuminate\Database\Capsule\Manager as DB;

return new class {
    public function up(): void
    {
        $columns = [
            'jurisdiction' => "VARCHAR(30) NOT NULL DEFAULT 'popia' AFTER id",
            'description' => "TEXT NULL AFTER name",
            'third_countries' => "JSON NULL AFTER recipients",
            'dpia_date' => "DATE NULL AFTER dpia_completed",
            'owner' => "VARCHAR(255) NULL AFTER status",
            'next_review_date' => "DATE NULL AFTER owner",
            'lawful_basis_code' => "VARCHAR(50) NULL AFTER lawful_basis"
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('privacy_processing_activity', $column)) {
                try {
                    DB::statement("ALTER TABLE privacy_processing_activity ADD COLUMN {$column} {$definition}");
                    echo "  Added column: {$column}\n";
                } catch (\Exception $e) {
                    echo "  Column {$column}: " . $e->getMessage() . "\n";
                }
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
