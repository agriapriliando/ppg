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
        Schema::create('admission_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('academic_year', 20);
            $table->string('wave', 50);
            $table->string('state', 24)->default('draft');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->timestamp('manual_closed_at')->nullable();
            $table->boolean('allow_correction_after_close')->default(false);
            $table->timestamp('correction_closes_at')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_role_snapshot', 32)->nullable();
            $table->string('action', 100);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('safe_changes');
            $table->text('reason')->nullable();
            $table->uuid('request_id');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['actor_id', 'created_at', 'id']);
        });
        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id');
            $table->string('provider_email')->nullable();
            $table->timestamp('linked_at');
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('application_settings');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('admission_periods');
    }
};
