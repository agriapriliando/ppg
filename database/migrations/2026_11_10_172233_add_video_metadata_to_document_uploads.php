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
        Schema::table('document_policy_versions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_video_files')->nullable();
        });
        foreach (['applicant_documents', 'period_documents'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->json('client_media_metadata')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['applicant_documents', 'period_documents'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('client_media_metadata');
            });
        }
        Schema::table('document_policy_versions', function (Blueprint $table): void {
            $table->dropColumn('max_video_files');
        });
    }
};
