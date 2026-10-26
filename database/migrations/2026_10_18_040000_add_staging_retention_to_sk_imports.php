<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sk_imports', function (Blueprint $table): void {
            $table->timestamp('staging_purged_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sk_imports', function (Blueprint $table): void {
            $table->dropColumn('staging_purged_at');
        });
    }
};
