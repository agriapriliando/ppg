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
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('login_id', 64)->nullable()->unique();
            $table->string('role', 32)->default('pendaftar_ppg')->index();
            $table->string('account_state', 32)->default('active');
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['login_id', 'role', 'account_state', 'must_change_password', 'last_login_at']);
        });
    }
};
