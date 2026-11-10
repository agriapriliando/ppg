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
        Schema::table('portal_quick_links', function (Blueprint $table) {
            $table->string('placement', 20)->default('both');
        });
        DB::table('portal_quick_links')->where('is_active', false)->update(['placement' => 'hidden']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portal_quick_links', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
    }
};
