<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Shop;
use App\Models\Product;

class MarketplaceSeeder extends Seeder
{
    public function run()
    {
        // Créer un utilisateur vendeur
        $user = User::firstOrCreate(
            ['email' => 'seller@example.com'],
            [
                'firstname' => 'Seller',
                'lastname' => 'Test',
                'password' => bcrypt('12345678'),
                'email_verified_at' => now(),
                'role' => 'user'
            ]
        );

        // Créer une catégorie
        $category = Category::firstOrCreate(
            ['slug' => 'semences'],
            ['name' => 'Semences']
        );

        // Créer une boutique
        $shop = Shop::firstOrCreate(
            ['slug' => 'agriseeds-pro'],
            [
                'user_id' => $user->id,
                'name' => 'AgriSeeds Pro',
                'description' => 'Vente de semences bio',
                'address' => 'Yaoundé',
                'city' => 'Yaoundé',
                'phone' => '677889900',
                'status' => 'active'
            ]
        );

        // Créer un produit
        $product = Product::firstOrCreate(
            ['slug' => 'mais-bio'],
            [
                'shop_id' => $shop->id,
                'category_id' => $category->id,
                'name' => 'Maïs Bio',
                'description' => '100% naturel, rendement élevé',
                'price' => 124.99,
                'stock' => 100,
                'status' => 'active'
            ]
        );

        $this->command->info('Données de marketplace créées avec succès.');
    }
}
