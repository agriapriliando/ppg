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
        Schema::create('applicant_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'admission_period_id']);
        });
        Schema::create('applicant_biodata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('applicant_workspaces')->restrictOnDelete();
            foreach (['full_name', 'birth_place', 'gender_code', 'religion_code', 'nationality_code', 'whatsapp_number', 'contact_email', 'other_phone', 'street_address', 'hamlet', 'rt', 'rw', 'village_name', 'province_code', 'city_code', 'district_code', 'postal_code', 'residence_type_code', 'transport_type_code', 'special_need_code'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['nik_encrypted', 'nisn_encrypted', 'mother_name_encrypted', 'kps_number_encrypted', 'npwp_encrypted'] as $field) {
                $table->text($field)->nullable();
            }
            $table->date('birth_date')->nullable();
            $table->boolean('is_kps_recipient')->nullable();
            $table->timestamps();
        });
        Schema::create('origin_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('applicant_workspaces')->restrictOnDelete();
            $table->foreignId('reference_dataset_id')->nullable()->constrained('reference_datasets')->restrictOnDelete();
            $table->string('pt_external_id', 64)->nullable();
            $table->string('prodi_external_id', 64)->nullable();
            $table->string('pt_name_snapshot')->nullable();
            $table->string('prodi_name_snapshot')->nullable();
            $table->timestamps();
        });
        Schema::create('sk_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_sk_participant_id')->nullable()->constrained('sk_participants')->restrictOnDelete();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('review_owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('submitted_name');
            $table->string('submitted_identifier', 100)->nullable();
            $table->string('whatsapp_number', 20);
            $table->string('access_path', 16);
            $table->string('status', 24)->default('pending');
            $table->uuid('public_reference')->unique();
            $table->uuid('request_key')->unique();
            $table->text('public_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('admission_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_period_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sk_participant_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->unique()->constrained('applicant_workspaces')->restrictOnDelete();
            $table->string('application_number', 100)->unique();
            $table->string('status', 24)->default('draf');
            $table->timestamp('submitted_at')->nullable();
            $table->date('tanggal_daftar')->nullable();
            $table->timestamp('last_resubmitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('config_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->unsignedInteger('submission_revision')->default(0);
            $table->string('nim', 50)->nullable()->unique();
            $table->timestamps();
            $table->unique(['user_id', 'admission_period_id']);
            $table->index(['admission_period_id', 'status', 'submitted_at', 'id'], 'application_queue_index');
        });
        Schema::create('whatsapp_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->nullable()->constrained('sk_claims')->restrictOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('applicant_workspaces')->restrictOnDelete();
            $table->string('number', 20);
            $table->string('method', 32)->default('manual_reply');
            $table->text('evidence_summary');
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('credential_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('claim_id')->constrained('sk_claims')->restrictOnDelete();
            $table->string('secret_hash');
            $table->string('target_number', 20);
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consent_text_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained('sk_claims')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision')->nullable();
            $table->timestamp('accepted_at');
        });
        Schema::create('application_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('consent_record_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('workspace_version');
            $table->char('content_digest', 64);
            $table->text('encrypted_snapshot');
            $table->timestamp('submitted_at');
            $table->unique(['application_id', 'revision']);
        });
        Schema::create('application_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('submission_revision');
            $table->string('action', 32);
            $table->boolean('identity_checked')->default(false);
            $table->boolean('sk_checked')->default(false);
            $table->boolean('data_checked')->default(false);
            $table->json('field_keys');
            $table->text('public_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['application_reviews', 'application_submissions', 'consent_records', 'credential_issuances', 'whatsapp_verifications', 'applications', 'admission_sequences', 'sk_claims', 'origin_education', 'applicant_biodata', 'applicant_workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
