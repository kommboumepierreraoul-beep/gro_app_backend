<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    
    // database/seeders/CategorySeeder.php
public function run(): void
{
    $categories = [
        ['name' => 'Céréales, Tubercules & Légumineuses', 'slug' => 'cereales'],
        ['name' => 'Fruits & Légumes Frais',              'slug' => 'fruits'],
        ['name' => 'Élevage & Produits Animaux',          'slug' => 'elevage'],
        ['name' => 'Pêche & Aquaculture',                 'slug' => 'peche'],
        ['name' => 'Produits de Transformation',          'slug' => 'transformation'],
        ['name' => 'Intrants & Équipements',              'slug' => 'intrants'],
        ['name' => 'Autres',                              'slug' => 'autres'],
    ];

    foreach ($categories as $cat) {
        \App\Models\Category::firstOrCreate(['slug' => $cat['slug']], $cat);
    }
}
}


