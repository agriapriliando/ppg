<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('applicant_workspaces', 'draft_payload')) {
            Schema::table('applicant_workspaces', function (Blueprint $table): void {
                $table->text('draft_payload')->nullable();
                $table->unsignedInteger('documents_version')->default(0);
            });
        }
        if (! Schema::hasColumn('applicant_documents', 'sha256')) {
            Schema::table('applicant_documents', function (Blueprint $table): void {
                $table->string('sha256', 64)->nullable();
                $table->timestamp('detached_at')->nullable();
                $table->timestamp('baseline_retained_at')->nullable();
            });
        }
        if (! Schema::hasColumn('application_submissions', 'request_id')) {
            Schema::table('application_submissions', function (Blueprint $table): void {
                $table->uuid('request_id')->nullable()->unique();
                $table->string('request_digest', 64)->nullable();
                $table->unsignedInteger('documents_version')->nullable();
            });
        }
        if (! Schema::hasTable('applicant_draft_operations')) {
            Schema::create('applicant_draft_operations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('applicant_workspaces')->cascadeOnDelete();
                $table->uuid('batch_id');
                $table->string('digest', 64);
                $table->unsignedInteger('result_version');
                $table->timestamp('created_at')->index();
                $table->unique(['workspace_id', 'batch_id']);
            });
        }
        if (! Schema::hasTable('applicant_upload_batches')) {
            Schema::create('applicant_upload_batches', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('workspace_id')->constrained('applicant_workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20)->default('uploading');
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->index(['workspace_id', 'status']);
            });
        }
        if (! Schema::hasTable('application_submission_documents')) {
            Schema::create('application_submission_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('submission_id')->constrained('application_submissions')->cascadeOnDelete();
                $table->foreignUuid('document_id')->constrained('applicant_documents')->restrictOnDelete();
                $table->string('file_name');
                $table->string('mime_type');
                $table->unsignedBigInteger('byte_size');
                $table->string('sha256', 64)->nullable();
                $table->foreignId('policy_version_id')->constrained('document_policy_versions')->restrictOnDelete();
            });
        }
        if (! Schema::hasIndex('application_submission_documents', 'submission_document_unique')) {
            Schema::table('application_submission_documents', fn (Blueprint $table) => $table->unique(['submission_id', 'document_id'], 'submission_document_unique'));
        }
        DB::table('applicant_documents')->where('status', 'available')
            ->whereIn('workspace_id', DB::table('applications')->whereNotNull('submitted_at')->select('workspace_id'))
            ->update(['baseline_retained_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_submission_documents');
        Schema::dropIfExists('applicant_upload_batches');
        Schema::dropIfExists('applicant_draft_operations');
        Schema::table('application_submissions', fn (Blueprint $table) => $table->dropColumn(['request_id', 'request_digest', 'documents_version']));
        Schema::table('applicant_documents', fn (Blueprint $table) => $table->dropColumn(['sha256', 'detached_at', 'baseline_retained_at']));
        Schema::table('applicant_workspaces', fn (Blueprint $table) => $table->dropColumn(['draft_payload', 'documents_version']));
    }
};
