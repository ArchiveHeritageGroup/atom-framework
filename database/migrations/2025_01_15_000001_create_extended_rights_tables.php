<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RightsStatements.org vocabulary
        Schema::create('rights_statement', function (Blueprint $table) {
            $table->id();
            $table->string('uri', 255)->unique();
            $table->string('code', 50)->unique();
            $table->enum('category', ['in-copyright', 'no-copyright', 'other']);
            $table->string('icon_filename', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rights_statement_i18n', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rights_statement_id')->constrained()->onDelete('cascade');
            $table->string('culture', 10)->default('en');
            $table->string('name', 255);
            $table->text('definition')->nullable();
            $table->text('scope_note')->nullable();
            $table->unique(['rights_statement_id', 'culture']);
        });

        // Creative Commons licenses
        Schema::create('creative_commons_license', function (Blueprint $table) {
            $table->id();
            $table->string('uri', 255)->unique();
            $table->string('code', 30)->unique();
            $table->string('version', 10)->default('4.0');
            $table->boolean('allows_adaptation')->default(true);
            $table->boolean('allows_commercial')->default(true);
            $table->boolean('requires_attribution')->default(true);
            $table->boolean('requires_sharealike')->default(false);
            $table->string('icon_filename', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('creative_commons_license_i18n', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_commons_license_id')->constrained()->onDelete('cascade');
            $table->string('culture', 10)->default('en');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unique(['creative_commons_license_id', 'culture'], 'cc_license_i18n_unique');
        });

        // TK Labels categories
        Schema::create('tk_label_category', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('color', 7)->default('#000000');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tk_label_category_i18n', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tk_label_category_id')->constrained()->onDelete('cascade');
            $table->string('culture', 10)->default('en');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unique(['tk_label_category_id', 'culture']);
        });

        // TK Labels
        Schema::create('tk_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tk_label_category_id')->constrained()->onDelete('cascade');
            $table->string('code', 30)->unique();
            $table->string('uri', 255)->unique();
            $table->string('icon_filename', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tk_label_i18n', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tk_label_id')->constrained()->onDelete('cascade');
            $table->string('culture', 10)->default('en');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->text('usage_guide')->nullable();
            $table->unique(['tk_label_id', 'culture']);
        });

        // Embargo management
        Schema::create('embargo', function (Blueprint $table) {
            $table->id();
            $table->integer('object_id')->index();
            $table->enum('embargo_type', ['full', 'metadata_only', 'digital_object', 'custom']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_perpetual')->default(false);
            $table->enum('status', ['active', 'expired', 'lifted', 'pending'])->default('pending');
            $table->integer('created_by')->nullable();
            $table->integer('lifted_by')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->text('lift_reason')->nullable();
            $table->boolean('notify_on_expiry')->default(true);
            $table->integer('notify_days_before')->default(30);
            $table->timestamps();
            $table->index(['object_id', 'status']);
            $table->index(['end_date', 'status']);
        });

        Schema::create('embargo_i18n', function (Blueprint $table) {
            $table->id();
            $table->foreignId('embargo_id')->constrained()->onDelete('cascade');
            $table->string('culture', 10)->default('en');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->text('public_message')->nullable();
            $table->unique(['embargo_id', 'culture']);
        });

        Schema::create('embargo_exception', function (Blueprint $table) {
            $table->id();
            $table->foreignId('embargo_id')->constrained()->onDelete('cascade');
            $table->enum('exception_type', ['user', 'group', 'ip_range', 'repository']);
            $table->integer('exception_id')->nullable();
            $table->string('ip_range_start', 45)->nullable();
            $table->string('ip_range_end', 45)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->integer('granted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('embargo_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('embargo_id')->constrained()->onDelete('cascade');
            $table->enum('action', ['created', 'modified', 'lifted', 'extended', 'exception_added', 'exception_removed']);
            $table->integer('user_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // Extended rights assignment (links objects to rights)
        Schema::create('extended_rights', function (Blueprint $table) {
            $table->id();
            $table->integer('object_id')->index();
            $table->foreignId('rights_statement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creative_commons_license_id')->nullable()->constrained()->nullOnDelete();
            $table->date('rights_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('rights_holder', 255)->nullable();
            $table->string('rights_holder_uri', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['object_id', 'is_primary'], 'ext_rights_primary_unique');
        });

        Schema::create('extended_rights_i18n', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extended_rights_id')->constrained()->onDelete('cascade');
            $table->string('culture', 10)->default('en');
            $table->text('rights_note')->nullable();
            $table->text('usage_conditions')->nullable();
            $table->text('copyright_notice')->nullable();
            $table->unique(['extended_rights_id', 'culture']);
        });

        // TK Labels assignment
        Schema::create('extended_rights_tk_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extended_rights_id')->constrained()->onDelete('cascade');
            $table->foreignId('tk_label_id')->constrained()->onDelete('cascade');
            $table->integer('community_id')->nullable();
            $table->text('community_note')->nullable();
            $table->date('assigned_date')->nullable();
            $table->timestamps();
            $table->unique(['extended_rights_id', 'tk_label_id'], 'ext_rights_tk_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extended_rights_tk_label');
        Schema::dropIfExists('extended_rights_i18n');
        Schema::dropIfExists('extended_rights');
        Schema::dropIfExists('embargo_audit');
        Schema::dropIfExists('embargo_exception');
        Schema::dropIfExists('embargo_i18n');
        Schema::dropIfExists('embargo');
        Schema::dropIfExists('tk_label_i18n');
        Schema::dropIfExists('tk_label');
        Schema::dropIfExists('tk_label_category_i18n');
        Schema::dropIfExists('tk_label_category');
        Schema::dropIfExists('creative_commons_license_i18n');
        Schema::dropIfExists('creative_commons_license');
        Schema::dropIfExists('rights_statement_i18n');
        Schema::dropIfExists('rights_statement');
    }
};
