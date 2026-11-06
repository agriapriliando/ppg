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
        Schema::create('period_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('display_name', 180);
            $table->string('extension', 16);
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size');
            $table->string('disk', 40)->default('local');
            $table->string('object_key');
            $table->string('status', 24)->default('available');
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('request_key', 100);
            $table->timestamps();
            $table->unique(['admission_period_id', 'uploaded_by', 'request_key'], 'period_documents_request_unique');
            $table->index(['admission_period_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('period_documents');
    }
};
