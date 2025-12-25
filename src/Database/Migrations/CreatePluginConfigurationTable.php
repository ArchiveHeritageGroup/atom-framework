<?php

declare(strict_types=1);

namespace Atom\Framework\Database\Migrations;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class CreatePluginConfigurationTable
{
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('atom_plugin')) {
            $schema->create('atom_plugin', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->unique();
                $table->string('class_name', 255);
                $table->string('version', 50)->nullable();
                $table->text('description')->nullable();
                $table->string('author', 255)->nullable();
                $table->string('category', 100)->default('general');
                $table->boolean('is_enabled')->default(false);
                $table->boolean('is_core')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->integer('load_order')->default(100);
                $table->string('plugin_path', 500)->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('enabled_at')->nullable();
                $table->timestamp('disabled_at')->nullable();
                $table->timestamps();
                $table->index('is_enabled');
                $table->index('category');
                $table->index('load_order');
            });
        }

        if (!$schema->hasTable('atom_plugin_dependency')) {
            $schema->create('atom_plugin_dependency', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plugin_id');
                $table->string('requires_plugin', 255);
                $table->string('min_version', 50)->nullable();
                $table->string('max_version', 50)->nullable();
                $table->boolean('is_optional')->default(false);
                $table->timestamps();
                $table->foreign('plugin_id')->references('id')->on('atom_plugin')->onDelete('cascade');
                $table->unique(['plugin_id', 'requires_plugin']);
                $table->index('requires_plugin');
            });
        }

        if (!$schema->hasTable('atom_plugin_hook')) {
            $schema->create('atom_plugin_hook', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plugin_id');
                $table->string('event_name', 255);
                $table->string('listener_class', 500);
                $table->string('listener_method', 255);
                $table->integer('priority')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->foreign('plugin_id')->references('id')->on('atom_plugin')->onDelete('cascade');
                $table->index('event_name');
                $table->index(['event_name', 'is_active']);
            });
        }

        if (!$schema->hasTable('atom_plugin_audit')) {
            $schema->create('atom_plugin_audit', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plugin_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 50);
                $table->json('previous_state')->nullable();
                $table->json('new_state')->nullable();
                $table->text('reason')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('plugin_id')->references('id')->on('atom_plugin')->onDelete('cascade');
                $table->index('user_id');
                $table->index('action');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        $schema = Capsule::schema();
        $schema->dropIfExists('atom_plugin_audit');
        $schema->dropIfExists('atom_plugin_hook');
        $schema->dropIfExists('atom_plugin_dependency');
        $schema->dropIfExists('atom_plugin');
    }
}
