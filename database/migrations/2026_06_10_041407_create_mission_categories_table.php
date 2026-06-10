<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('icon', 50)->nullable();   // nom icône Lucide React
            $table->string('color', 7)->nullable();   // hex, ex: "#154212"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed des catégories de base GRO
        DB::table('mission_categories')->insert([
            ['name' => 'Service & proximité',   'slug' => 'service-proximite',  'icon' => 'HandHelping',    'color' => '#154212', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Agricole & terrain',    'slug' => 'agricole-terrain',   'icon' => 'Sprout',         'color' => '#2d5a27', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Scolaire & formation',  'slug' => 'scolaire-formation', 'icon' => 'GraduationCap',  'color' => '#3b6934', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Événementiel',          'slug' => 'evenementiel',       'icon' => 'CalendarDays',   'color' => '#185fa5', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mission stratégique',   'slug' => 'mission-strategique', 'icon' => 'Target',         'color' => '#854f0b', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_categories');
    }
};
