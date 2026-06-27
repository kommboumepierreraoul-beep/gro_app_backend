<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('shop', 'category')->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

   public function store(Request $request)
{
    $request->validate([
        'category_id' => 'nullable|exists:categories,id',
        'name' => 'required|string|max:255',
        'description' => 'required|string',
        'price' => 'required|numeric',
        'stock' => 'required|integer',
        'images.*' => 'image|max:10240'
    ]);

    $images = [];

    if ($request->hasFile('images')) {

        foreach ($request->file('images') as $image) {

            $path = $image->store(
                'products',
                'public'
            );

            $images[] = asset('storage/' . $path);
        }
    }

    $product = Product::create([
        'shop_id' => 7, // temporairement
        'category_id' => $request->category_id,
        'name' => $request->name,
        'slug' => Str::slug($request->name) . '-' . uniqid(),
        'description' => $request->description,
        'price' => $request->price,
        'stock' => $request->stock,
        'images' => $images,
        'status' => 'active',
    ]);

    return response()->json([
        'success' => true,
        'data' => $product
    ]);
}
    public function show($id)
    {
        $product = Product::with('shop', 'category', 'reviews.user')->find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $product]);
    }
public function update(Request $request, $id)
{
    $product = Product::findOrFail($id);

    // Vérifier que l'utilisateur est bien le propriétaire du produit
    if ($product->shop->user_id !== $request->user()->id) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Validation des champs
    $request->validate([
        'name'             => 'sometimes|required|string|max:255',
        'category_id'      => 'nullable|exists:categories,id',
        'description'      => 'nullable|string',
        'price'            => 'sometimes|required|numeric|min:0',
        'unit_price'       => 'nullable|numeric|min:0',
        'weight'           => 'nullable|numeric',
        'stock'            => 'nullable|integer|min:0',
        'stock_quantity'   => 'nullable|integer|min:0',
        'listing_type'     => 'nullable|in:sale,rent',
        'delivery_condition' => 'nullable|string|max:1000',
        'variety'          => 'nullable|string|max:255',
        'origin'           => 'nullable|string|max:255',
        'certification'    => 'nullable|string|max:255',
        'harvest_date'     => 'nullable|date',
        'expiration_date'  => 'nullable|date',
        'is_featured'      => 'nullable|boolean',
        'status'           => 'nullable|string',
    ]);

    // Récupérer toutes les données sauf images (gérées séparément)
    $data = $request->except(['images', 'existing_images']);

    // Gestion des images : fusion des anciennes (existing_images) et des nouvelles (uploadées)
    $existingImages = json_decode($request->input('existing_images', '[]'), true);
    $newImages = $this->handleImages($request);
    $data['images'] = array_merge($existingImages, $newImages);

    // Mise à jour du slug si le nom change
    if ($request->has('name') && $request->name !== $product->name) {
        $product->slug = Str::slug($request->name) . '-' . $product->id . '-' . time();
    }

    // Assignation massive (sauf slug qui n'est pas fillable)
    $product->fill($data);
    $product->save();

    return response()->json(['success' => true, 'data' => $product]);
}

    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $product->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

public function upload(Request $request)
{
    $request->validate([
        'image' => 'required|image|max:10240'
    ]);

    $path = $request->file('image')->store(
        'products',
        'public'
    );

    return response()->json([
        'success' => true,
        'url' => asset('storage/'.$path)
    ]);
}
private function handleImages(Request $request): array
{
    $urls = [];
    if ($request->hasFile('images')) {
        $files = $request->file('images');
        if (!is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            if ($file->isValid()) {
                $path = $file->store('products', 'public');
                $urls[] = asset('storage/' . $path);
            }
        }
    }
    return $urls;
}
}
