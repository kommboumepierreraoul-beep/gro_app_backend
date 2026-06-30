<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Catégories existantes
            ['name' => 'Céréales, Tubercules & Légumineuses', 'slug' => 'cereales'],
            ['name' => 'Fruits & Légumes Frais',              'slug' => 'fruits'],
            ['name' => 'Élevage & Produits Animaux',          'slug' => 'elevage'],
            ['name' => 'Pêche & Aquaculture',                 'slug' => 'peche'],
            ['name' => 'Produits de Transformation',          'slug' => 'transformation'],
            ['name' => 'Intrants & Équipements',              'slug' => 'intrants'],
            ['name' => 'Autres',                              'slug' => 'autres'],

            // Nouvelles catégories
            ['name' => 'Engrais',                             'slug' => 'engrais'],
            ['name' => 'Herbicide',                           'slug' => 'herbicide'],
            ['name' => 'Fongicides',                          'slug' => 'fongicides'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}