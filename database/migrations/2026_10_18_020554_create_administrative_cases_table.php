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
        Schema::create('administrative_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->nullable()->constrained('sk_claims')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('state', 24)->default('open');
            $table->string('context_hash', 64)->nullable();
            $table->text('reason');
            $table->text('resolution')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrative_cases');
    }
};
