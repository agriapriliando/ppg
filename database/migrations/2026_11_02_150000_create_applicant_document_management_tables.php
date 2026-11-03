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
        Schema::create('document_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_period_id')->constrained('admission_periods')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('allowed_extensions');
            $table->unsignedBigInteger('max_file_size_bytes');
            $table->unsignedBigInteger('workspace_quota_bytes')->nullable();
            $table->text('general_instructions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['admission_period_id', 'version'], 'doc_policy_period_version_unique');
        });

        Schema::create('applicant_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained('applicant_workspaces')->restrictOnDelete();
            $table->foreignId('admission_period_id')->constrained('admission_periods')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('policy_version_id')->constrained('document_policy_versions')->restrictOnDelete();
            $table->string('original_name');
            $table->string('display_name', 180);
            $table->string('extension', 20);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->string('disk', 40)->default('local');
            $table->string('object_key')->unique();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('available');
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('request_key', 64);
            $table->timestamps();

            $table->unique(['workspace_id', 'uploaded_by', 'request_key'], 'applicant_docs_idempotency_unique');
            $table->index(['workspace_id', 'status', 'created_at'], 'applicant_docs_workspace_status_idx');
            $table->index(['admission_period_id', 'status'], 'applicant_docs_period_status_idx');
        });

        Schema::create('document_deletion_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('document_id')->index();
            $table->string('disk', 40);
            $table->string('object_key');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at'], 'doc_del_tasks_status_attempt_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_deletion_tasks');
        Schema::dropIfExists('applicant_documents');
        Schema::dropIfExists('document_policy_versions');
    }
};
