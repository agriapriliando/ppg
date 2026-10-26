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
        Schema::table('applications', function (Blueprint $table): void {
            $table->unsignedInteger('ownership_epoch')->default(0);
            $table->timestamp('current_owner_submitted_at')->nullable();
        });
        foreach (['application_reviews', 'application_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedInteger('ownership_epoch')->default(0);
            });
        }
        Schema::create('application_ownership_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('ownership_epoch');
            $table->foreignId('old_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('new_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('old_workspace_id')->constrained('applicant_workspaces')->restrictOnDelete();
            $table->foreignId('new_workspace_id')->constrained('applicant_workspaces')->restrictOnDelete();
            $table->foreignId('target_claim_id')->constrained('sk_claims')->restrictOnDelete();
            $table->foreignId('administrative_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('previous_submission_revision');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['application_id', 'ownership_epoch'], 'application_ownership_epoch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('application_ownership_transfers') && DB::table('application_ownership_transfers')->exists()) {
            throw new RuntimeException('Riwayat koreksi kepemilikan sudah terisi. Gunakan forward fix atau restore tervalidasi agar batas privasi histori tidak hilang.');
        }
        Schema::dropIfExists('application_ownership_transfers');
        foreach (['application_reviews', 'application_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('ownership_epoch');
            });
        }
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['ownership_epoch', 'current_owner_submitted_at']);
        });
    }
};
