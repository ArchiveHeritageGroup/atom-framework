<?php
/**
 * Fix privacy_breach column names to match service
 */
use Illuminate\Database\Capsule\Manager as DB;

return new class {
    public function up(): void
    {
        // Rename discovery_date to detected_date if needed
        if ($this->columnExists('privacy_breach', 'discovery_date') && 
            !$this->columnExists('privacy_breach', 'detected_date')) {
            try {
                DB::statement("ALTER TABLE privacy_breach CHANGE discovery_date detected_date DATETIME NOT NULL");
                echo "  Renamed discovery_date to detected_date\n";
            } catch (\Exception $e) {
                echo "  Error: " . $e->getMessage() . "\n";
            }
        } elseif (!$this->columnExists('privacy_breach', 'detected_date')) {
            try {
                DB::statement("ALTER TABLE privacy_breach ADD COLUMN detected_date DATETIME NOT NULL AFTER breach_date");
                echo "  Added detected_date column\n";
            } catch (\Exception $e) {
                echo "  Error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  detected_date column already exists\n";
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
