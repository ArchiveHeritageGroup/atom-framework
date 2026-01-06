<?php
/**
 * Fix privacy_breach_i18n columns to match service expectations
 */
use Illuminate\Database\Capsule\Manager as DB;

return new class {
    public function up(): void
    {
        $table = 'privacy_breach_i18n';
        
        // Add missing columns
        $columns = [
            'title' => "VARCHAR(255) DEFAULT NULL AFTER culture",
            'cause' => "TEXT AFTER description",
            'remedial_actions' => "TEXT AFTER impact_assessment",
            'lessons_learned' => "TEXT AFTER remedial_actions"
        ];
        
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists($table, $column)) {
                try {
                    DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                    echo "  Added {$table}.{$column}\n";
                } catch (\Exception $e) {
                    echo "  {$column}: " . $e->getMessage() . "\n";
                }
            } else {
                echo "  {$column} already exists\n";
            }
        }
        
        // Rename remediation_notes to remedial_actions if it exists
        if ($this->columnExists($table, 'remediation_notes') && !$this->columnExists($table, 'remedial_actions')) {
            try {
                DB::statement("ALTER TABLE {$table} CHANGE remediation_notes remedial_actions TEXT");
                echo "  Renamed remediation_notes to remedial_actions\n";
            } catch (\Exception $e) {
                echo "  Rename error: " . $e->getMessage() . "\n";
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
