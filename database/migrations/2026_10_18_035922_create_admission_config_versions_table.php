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
        Schema::create('admission_config_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('pmb_config');
            $table->json('reference_version_ids');
            $table->foreignId('consent_text_id')->constrained()->restrictOnDelete();
            $table->string('consent_version', 40);
            $table->char('content_digest', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['admission_period_id', 'version'], 'admission_config_period_version');
            $table->unique(['admission_period_id', 'content_digest'], 'admission_config_period_digest');
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('config_version_id')->nullable()->constrained('admission_config_versions')->restrictOnDelete();
        });
        Schema::table('application_submissions', function (Blueprint $table): void {
            $table->foreignId('config_version_id')->nullable()->constrained('admission_config_versions')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('config_version_id');
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('config_version_id');
        });
        Schema::dropIfExists('admission_config_versions');
    }
};
