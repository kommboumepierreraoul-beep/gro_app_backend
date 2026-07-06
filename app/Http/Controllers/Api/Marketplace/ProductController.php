<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ActivityLog;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // Liste des produits avec filtres
    public function index(Request $request)
    {
        $query = Product::with([
            'shop:id,name,slug,logo',
            'category:id,name,slug'
        ])->where('status', 'active')
         ->where('approval_status', 'approved'); // Afficher seulement les produits approuvés

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->search) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        if ($request->featured) {
            $query->where('is_featured', true);
        }

        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        $products = $query
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Transformer les logos en URLs complètes
        $products->getCollection()->transform(function ($product) {
            if ($product->shop && $product->shop->logo) {
                $product->shop->logo = asset('storage/' . $product->shop->logo);
            }
            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // Créer un produit
    public function store(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'You need a shop first'
            ], 400);
        }

        $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'required|string',

            'price'               => 'required|numeric|min:0',
            'unit_price'          => 'nullable|numeric|min:0',

            'stock'               => 'nullable|integer|min:0',
            'stock_quantity'      => 'nullable|integer|min:0',

            'category_id'         => 'nullable|exists:categories,id',

            'images'              => 'nullable',
            'images.*'            => 'nullable|file|image|max:10240',

            'weight'              => 'nullable|numeric',
            'is_featured'         => 'nullable|boolean',

            'listing_type'        => 'nullable|in:sale,rent',

            'delivery_condition'  => 'nullable|string|max:1000',

            'variety'             => 'nullable|string|max:255',
            'origin'              => 'nullable|string|max:255',
            'certification'       => 'nullable|string|max:255',

            'harvest_date'        => 'nullable|date',
            'expiration_date'     => 'nullable|date',
        ]);

        $product = Product::create([
            'shop_id'             => $shop->id,
            'category_id'         => $request->category_id,

            'name'                => $request->name,
            'slug'                => Str::slug($request->name) . '-' . time(),
            'description'         => $request->description,

            'price'               => $request->price,
            'unit_price'          => $request->unit_price,

            'stock'               => $request->stock ?? 0,
            'stock_quantity'      => $request->stock_quantity,

            // ✅ Après
'images' => $this->handleImages($request),

            'weight'              => $request->weight,

            'listing_type'        => $request->listing_type ?? 'sale',

            'delivery_condition'  => $request->delivery_condition,

            'variety'             => $request->variety,
            'origin'              => $request->origin,
            'certification'       => $request->certification,

            'harvest_date'        => $request->harvest_date,
            'expiration_date'     => $request->expiration_date,

            'is_featured'         => $request->is_featured ?? false,
            'status'              => 'active',
            'approval_status'     => 'pending',
        ]);

        // Enregistrer l'activité
        ActivityLog::log(
            'product_added',
            "Nouveau produit ajouté: \"{$product->name}\" par {$shop->name}",
            'Product',
            $product->id
        );

        return response()->json([
            'success' => true,
            'data' => $product
        ], 201);
    }

    // Voir un produit
    public function show($id)
    {
        $product = Product::with([
            'shop:id,user_id,name,slug,logo',
            'category:id,name,slug',
            'reviews.user:id,firstname,lastname',
        ])->findOrFail($id);

        $product->average_rating = $product->average_rating;

        // Transformer le logo du shop en URL complète
        if ($product->shop && $product->shop->logo) {
            $product->shop->logo = asset('storage/' . $product->shop->logo);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    // Modifier un produit
   public function update(Request $request, $id)
{
    $product = Product::findOrFail($id);

    if ($product->shop->user_id !== $request->user()->id) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $data = $request->only([
        'name', 'description', 'price', 'unit_price', 'stock', 'stock_quantity',
        'category_id', 'weight', 'listing_type', 'delivery_condition',
        'variety', 'origin', 'certification', 'harvest_date', 'expiration_date',
        'is_featured', 'status'
    ]);

    // Gestion des images
    $existingImages = json_decode($request->input('existing_images', '[]'), true);
    $newImages = $this->handleImages($request);

    // Fusion : on garde les anciennes + les nouvelles
    $allImages = array_merge($existingImages, $newImages);
    $data['images'] = $allImages;

    $product->update($data);

    return response()->json(['success' => true, 'data' => $product]);
}
    // Supprimer un produit
    public function destroy(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        if ($product->shop->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted'
        ]);
    }

    // Produits mis en avant
    public function featured()
    {
        $products = Product::with([
            'shop:id,name,logo',
            'category:id,name'
        ])
        ->where('status', 'active')
        ->where('approval_status', 'approved') // Afficher seulement les produits approuvés
        ->where('is_featured', true)
        ->limit(10)
        ->get();

        // Transformer les logos en URLs complètes
        $products->transform(function ($product) {
            if ($product->shop && $product->shop->logo) {
                $product->shop->logo = asset('storage/' . $product->shop->logo);
            }
            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
private function handleImages(Request $request): array
{
    $urls = [];

    // Vérifier si le champ 'images' est présent et n'est pas vide
    if ($request->hasFile('images')) {
        $files = $request->file('images');
        
        // S'assurer que c'est bien un tableau (si plusieurs images)
        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if ($file->isValid()) {
                $urls[] = app(CloudinaryService::class)
                    ->uploadImageUrl($file, 'agripulse/products');
            }
        }
    }

    // Cas 2 — Si aucune image n'est uploadée, on retourne un tableau vide
    return $urls;
}
// Dans ProductController.php, ajoutez cette méthode
public function myShopProducts(Request $request)
{
    $shop = $request->user()->shop;
    
    if (!$shop) {
        return response()->json(['success' => false, 'message' => 'Shop not found'], 404);
    }

    $products = Product::where('shop_id', $shop->id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json(['success' => true, 'data' => $products]);
}
// app/Http/Controllers/Api/Marketplace/ProductController.php

public function myProducts(Request $request)
{
    $user = $request->user();
    $shop = $user->shop;

    if (!$shop) {
        return response()->json([
            'success' => false,
            'message' => 'Vous n’avez pas encore de boutique.'
        ], 404);
    }

    $products = Product::where('shop_id', $shop->id)
        ->with(['category:id,name'])
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $products
    ]);
}

}
