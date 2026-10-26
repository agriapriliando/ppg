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
        Schema::create('reference_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('version', 64)->unique();
            $table->string('source');
            $table->string('state', 24)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();
        });
        Schema::create('reference_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('reference_datasets')->restrictOnDelete();
            $table->string('group', 32);
            $table->string('code', 20);
            $table->string('label');
            $table->unique(['dataset_id', 'group', 'code']);
        });
        Schema::create('reference_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('reference_datasets')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('parent_code', 20)->nullable();
            $table->string('level', 20);
            $table->string('name');
            $table->unique(['dataset_id', 'code']);
        });
        Schema::create('reference_pt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('reference_datasets')->restrictOnDelete();
            $table->string('external_id', 64);
            $table->string('name');
            $table->unique(['dataset_id', 'external_id']);
        });
        Schema::create('reference_prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('reference_datasets')->restrictOnDelete();
            $table->string('external_id', 64);
            $table->string('pt_external_id', 64);
            $table->string('name');
            $table->unique(['dataset_id', 'external_id']);
        });
        Schema::create('consent_texts', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 32);
            $table->string('version', 40);
            $table->text('body');
            $table->timestamp('published_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unique(['purpose', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_texts');
        Schema::dropIfExists('reference_prodi');
        Schema::dropIfExists('reference_pt');
        Schema::dropIfExists('reference_regions');
        Schema::dropIfExists('reference_options');
        Schema::dropIfExists('reference_datasets');
    }
};
