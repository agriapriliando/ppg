<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('origin_education', function (Blueprint $table): void {
            $table->string('degree_level', 2)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->json('education_history')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('origin_education', function (Blueprint $table): void {
            $table->dropColumn(['degree_level', 'graduation_year', 'education_history']);
        });
    }
};
