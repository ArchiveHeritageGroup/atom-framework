<?php
/**
 * Add class_name column to atom_plugin if missing
 */
use Illuminate\Database\Capsule\Manager as DB;

return new class {
    public function up(): void
    {
        if (!$this->columnExists('atom_plugin', 'class_name')) {
            try {
                DB::statement("ALTER TABLE atom_plugin ADD COLUMN class_name VARCHAR(255) NOT NULL AFTER name");
                echo "  Added atom_plugin.class_name\n";
                
                // Update existing rows
                DB::statement("UPDATE atom_plugin SET class_name = CONCAT(name, 'Configuration') WHERE class_name = ''");
                echo "  Updated existing plugin class_name values\n";
            } catch (\Exception $e) {
                echo "  Error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  class_name column already exists\n";
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
