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
        Schema::create('portal_quick_links', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('url', 500);
            $table->string('description', 255)->nullable();
            $table->string('icon', 50)->default('bi-box-arrow-up-right');
            $table->string('button_color', 30)->default('purple');
            $table->string('target', 20)->default('_blank');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Data awal tautan penting
        DB::table('portal_quick_links')->insert([
            [
                'title' => 'LMS PPG',
                'url' => 'https://lms.iaknpky.ac.id',
                'description' => 'Sistem Pembelajaran Daring PPG IAKN Palangka Raya',
                'icon' => 'bi-mortarboard-fill',
                'button_color' => 'purple',
                'target' => '_blank',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'UKIN PPG',
                'url' => 'https://ukin.kemdikbud.go.id',
                'description' => 'Uji Kinerja & Portofolio PPG Dalam Jabatan',
                'icon' => 'bi-journal-check',
                'button_color' => 'blue',
                'target' => '_blank',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Web IAKN Palangka Raya',
                'url' => 'https://iaknpky.ac.id',
                'description' => 'Website Resmi Institut Agama Kristen Negeri Palangka Raya',
                'icon' => 'bi-globe2',
                'button_color' => 'gold',
                'target' => '_blank',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_quick_links');
    }
};
