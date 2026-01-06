<?php
/**
 * Test migration to verify migrate run does auto-pull
 */
use Illuminate\Database\Capsule\Manager as DB;

return new class {
    public function up(): void
    {
        echo "  ✓ Test migration executed successfully!\n";
        echo "  This confirms migrate run auto-pull is working.\n";
    }
};
