<?php

namespace AtomFramework\Museum\Database\Migrations;

use AtomFramework\Core\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Psr\Log\LoggerInterface;

class CreateMuseumObjectPropertiesTable
{
    private DatabaseManager $db;
    private LoggerInterface $logger;

    public function __construct(DatabaseManager $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Run the migration.
     *
     * @return void
     */
    public function up(): void
    {
        $schema = $this->db->getSchemaBuilder();

        if ($schema->hasTable('museum_object_properties')) {
            $this->logger->info('Table museum_object_properties already exists, skipping creation');

            return;
        }

        $this->logger->info('Creating museum_object_properties table');

        $schema->create('museum_object_properties', function (Blueprint $table) {
            $table->id();
            $table->integer('information_object_id')->unsigned();
            $table->string('work_type', 100)->index();
            $table->json('materials')->nullable();
            $table->json('techniques')->nullable();
            $table->json('measurements')->nullable();
            $table->string('creation_date_earliest', 50)->nullable();
            $table->string('creation_date_latest', 50)->nullable();
            $table->text('inscription')->nullable();
            $table->text('condition_notes')->nullable();
            $table->text('provenance')->nullable();
            $table->string('style_period', 100)->nullable();
            $table->string('cultural_context', 100)->nullable();
            $table->timestamps();

            // Add foreign key constraint
            $table->foreign('information_object_id')
                  ->references('id')
                  ->on('information_object')
                  ->onDelete('cascade');

            // Add index for faster lookups
            $table->index('information_object_id', 'idx_museum_info_object');
        });

        $this->logger->info('Successfully created museum_object_properties table');
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down(): void
    {
        $schema = $this->db->getSchemaBuilder();

        if (!$schema->hasTable('museum_object_properties')) {
            $this->logger->info('Table museum_object_properties does not exist, skipping drop');

            return;
        }

        $this->logger->info('Dropping museum_object_properties table');

        $schema->dropIfExists('museum_object_properties');

        $this->logger->info('Successfully dropped museum_object_properties table');
    }

    /**
     * Check if migration has been run.
     *
     * @return bool
     */
    public function hasRun(): bool
    {
        $schema = $this->db->getSchemaBuilder();

        return $schema->hasTable('museum_object_properties');
    }
}
