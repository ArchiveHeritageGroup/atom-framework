<?php
/**
 * Migration: Extended Levels of Description
 * 
 * Adds GLAM sector-specific levels: Museum, Library, Gallery, DAM.
 * Uses NAME-based lookups - safe to run on any installation.
 */

require_once dirname(__DIR__, 2) . '/src/Migrations/ExtendedLevelsOfDescription.php';

class ExtendedLevelsOfDescriptionMigration
{
    public function up(): array
    {
        return \AtomExtensions\Migrations\ExtendedLevelsOfDescription::up();
    }

    public function down(): array
    {
        return \AtomExtensions\Migrations\ExtendedLevelsOfDescription::down();
    }
}
