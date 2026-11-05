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
        Schema::table('applicant_biodata', function (Blueprint $table) {
            $table->string('peg_id', 100)->nullable();
            $table->text('nuptk_encrypted')->nullable();
            $table->string('residence_region', 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_biodata', function (Blueprint $table) {
            $table->dropColumn(['peg_id', 'nuptk_encrypted', 'residence_region']);
        });
    }
};
