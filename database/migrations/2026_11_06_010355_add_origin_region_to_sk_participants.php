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
        Schema::table('sk_participants', function (Blueprint $table) {
            $table->foreignId('origin_reference_dataset_id')->nullable()->constrained('reference_datasets')->restrictOnDelete();
            $table->string('origin_city_code', 64)->nullable();
            $table->string('origin_city_name')->nullable();
            $table->string('origin_province_code', 64)->nullable();
            $table->string('origin_province_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sk_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_reference_dataset_id');
            $table->dropColumn(['origin_city_code', 'origin_city_name', 'origin_province_code', 'origin_province_name']);
        });
    }
};
