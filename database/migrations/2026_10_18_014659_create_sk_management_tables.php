<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('period_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['admission_period_id', 'user_id']);
        });
        Schema::create('sk_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('private_path');
            $table->char('file_sha256', 64);
            $table->string('state', 24)->default('uploaded');
            $table->json('counts')->nullable();
            $table->text('safe_summary')->nullable();
            $table->char('preview_hash', 64)->nullable();
            $table->unsignedInteger('dataset_version')->default(0);
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sk_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->string('sk_number', 150);
            $table->date('sk_date');
            $table->unsignedInteger('sk_order');
            $table->string('official_participant_id', 100)->nullable();
            $table->string('name_on_sk');
            $table->string('normalized_name');
            $table->foreignId('assigned_operator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('claimed_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('last_import_id')->nullable()->constrained('sk_imports')->restrictOnDelete();
            $table->string('roster_state', 24)->default('active');
            $table->boolean('admin_hold')->default(false);
            $table->text('hold_reason')->nullable();
            $table->text('internal_note')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['admission_period_id', 'sk_number', 'sk_order'], 'sk_business_key');
            $table->unique(['admission_period_id', 'official_participant_id'], 'sk_official_identifier');
            $table->index(['admission_period_id', 'assigned_operator_id', 'id'], 'sk_assignment_index');
            $table->index(['admission_period_id', 'normalized_name'], 'sk_name_index');
        });
        Schema::create('sk_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sk_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('normalized_payload');
            $table->string('result', 24);
            $table->json('errors');
            $table->unique(['sk_import_id', 'row_number']);
        });
        Schema::create('assignment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sk_participant_id')->constrained()->restrictOnDelete();
            $table->foreignId('old_operator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('new_operator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('mode', 24);
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_events');
        Schema::dropIfExists('sk_import_rows');
        Schema::dropIfExists('sk_participants');
        Schema::dropIfExists('sk_imports');
        Schema::dropIfExists('period_operators');
    }
};
