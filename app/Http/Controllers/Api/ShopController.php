<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\CloudinaryService;
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
    $existingShop = $user->shop;

    if ($existingShop && $existingShop->status !== 'rejected') {
        return response()->json([
            'success' => false,
            'message' => $existingShop->status === 'pending'
                ? 'Votre demande de boutique est deja en attente de validation.'
                : 'You already have a shop',
            'data' => $existingShop,
        ], 400);
    }

    // 2. Validation
   $request->validate([
    'name'        => 'required|string|max:255',
    'description' => 'nullable|string',
    'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
    'banner'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
    'address'     => 'nullable|string',
    'city'        => 'nullable|string',
    'phone'       => 'nullable|string',
]);

    $logoPath = null;
    $bannerPath = null;

    // 3. Gestion correcte des fichiers
    if ($request->hasFile('logo')) {
        $logoPath = app(CloudinaryService::class)
            ->uploadImageUrl($request->file('logo'), 'agripulse/shops/logos');
    }
    if ($request->hasFile('banner')) {
        $bannerPath = app(CloudinaryService::class)
            ->uploadImageUrl($request->file('banner'), 'agripulse/shops/banners');
    }

    $payload = [
        'user_id'     => $user->id,
        'name'        => $request->name,
        'slug'        => Str::slug($request->name) . '-' . $user->id,
        'description' => $request->description,
        'logo'        => $logoPath ?? $existingShop?->logo,
        'banner'      => $bannerPath ?? $existingShop?->banner,
        'address'     => $request->address,
        'city'        => $request->city,
        'phone'       => $request->phone,
        'status'      => 'pending',
    ];

    if ($existingShop) {
        $existingShop->update($payload);
        $shop = $existingShop->fresh();
    } else {
        $shop = Shop::create($payload);
    }

    return response()->json([
        'success' => true,
        'message' => 'Votre demande de boutique a ete envoyee. Elle sera examinee par un administrateur.',
        'data' => $shop,
    ], 201);
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

    $shop->logo = $shop->logo && !str_starts_with($shop->logo, 'http')
        ? asset('storage/' . $shop->logo)
        : $shop->logo;
    $shop->banner = $shop->banner && !str_starts_with($shop->banner, 'http')
        ? asset('storage/' . $shop->banner)
        : $shop->banner;

    return response()->json(['success' => true, 'data' => $shop]);

    // ✅ Passer en seller automatiquement
}
}
