<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ShopController extends Controller
{
    public function index(Request $request)
{
    // On récupère uniquement la boutique appartenant à l'utilisateur connecté
    $shop = \App\Models\Shop::where('user_id', $request->user()->id)->first();

    if (!$shop) {
        return response()->json(['success' => false, 'message' => 'No shop found'], 404);
    }

    return response()->json(['success' => true, 'data' => $shop]);
}

    public function store(Request $request)
{
    

    // 1. Récupérer l'utilisateur authentifié
    $user = $request->user();

    // 2. Validation
   $request->validate([
    'name'        => 'required|string|max:255',
    'description' => 'nullable|string',
    'logo'        => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 'required' pour forcer le test
    'banner'      => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
    'address'     => 'nullable|string',
    'city'        => 'nullable|string',
    'phone'       => 'nullable|string',
]);

    $logoPath = null;
    $bannerPath = null;

    // 3. Gestion correcte des fichiers
    if ($request->hasFile('logo')) {
        // store() retourne le chemin relatif (ex: shops/logos/nomimage.jpg)
        $logoPath = $request->file('logo')->store('shops/logos', 'public');
    }
    if ($request->hasFile('banner')) {
        $bannerPath = $request->file('banner')->store('shops/banners', 'public');
    }

    // 4. Création avec les chemins de stockage
    $shop = Shop::create([
        'user_id'     => $user->id,
        'name'        => $request->name,
        'slug'        => Str::slug($request->name) . '-' . $user->id,
        'description' => $request->description,
        'logo'        => $logoPath,   // On stocke le chemin relatif, c'est la bonne pratique
        'banner'      => $bannerPath, // On ne met pas asset() ici pour garder la flexibilité
        'address'     => $request->address,
        'city'        => $request->city,
        'phone'       => $request->phone,
        'status'      => 'active',
    ]);

    return response()->json(['success' => true, 'data' => $shop], 201);
}

    public function show($id)
    {
        $shop = Shop::with('user', 'products')->find($id);
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $shop]);
    }

    public function update(Request $request, $id)
    {
        $shop = Shop::find($id);
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        
        $shop->update($request->only(['name', 'description', 'logo', 'banner', 'address', 'city', 'phone', 'status']));
        
        if ($request->has('name')) {
            $shop->slug = Str::slug($request->name) . '-' . uniqid();
            $shop->save();
        }
        return response()->json(['success' => true, 'data' => $shop]);
    }

    public function destroy($id)
    {
        $shop = Shop::find($id);
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $shop->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
    public function myShopProfile(Request $request)
{
    $user = $request->user();
    $shop = $user->shop;

    if (!$shop) {
        return response()->json(['success' => false, 'message' => 'Boutique introuvable'], 404);
    }

    $shop->logo = $shop->logo ? asset('storage/' . $shop->logo) : null;
    $shop->banner = $shop->banner ? asset('storage/' . $shop->banner) : null;

    return response()->json(['success' => true, 'data' => $shop]);
}
}