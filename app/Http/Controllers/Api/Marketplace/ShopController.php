<?php
namespace App\Http\Controllers\Api\Marketplace;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    // Liste toutes les boutiques actives
    public function index()
    {
        $shops = Shop::where('status', 'active')
            ->withCount('products')
            ->with('user:id,firstname,lastname')
            ->paginate(15);
        $user->update(['role' => 'seller']);
    return response()->json(['success' => true, 'data' => $shops]);
    }

    // Créer sa boutique
    public function store(Request $request)
{
    $user = $request->user();

    if ($user->shop) {
        $user->update(['role' => 'seller']);
    return response()->json([
            'success' => false,
            'message' => 'You already have a shop'
        ], 400);
    }

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

    if ($request->hasFile('logo')) {
        $logoPath = $request
            ->file('logo')
            ->store('shops/logos', 'public');
    }

    if ($request->hasFile('banner')) {
        $bannerPath = $request
            ->file('banner')
            ->store('shops/banners', 'public');
    }

    $shop = Shop::create([
        'user_id'     => $user->id,
        'name'        => $request->name,
        'slug'        => Str::slug($request->name) . '-' . $user->id,
        'description' => $request->description,
        'logo'        => $logoPath,
        'banner'      => $bannerPath,
        'address'     => $request->address,
        'city'        => $request->city,
        'phone'       => $request->phone,
        'status'      => 'active',
    ]);

    $user->update(['role' => 'seller']);
    return response()->json([
        'success' => true,
        'data' => $shop
    ], 201);
}

    // Voir une boutique
    public function show($id)
    {
        $shop = Shop::with(['user:id,firstname,lastname', 'products' => function ($q) {
            $q->where('status', 'active')->limit(10);
        }])->findOrFail($id);

        $user->update(['role' => 'seller']);
    return response()->json(['success' => true, 'data' => $shop]);
    }

    // Modifier sa boutique
    public function update(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);

        if ($shop->user_id !== $request->user()->id) {
            $user->update(['role' => 'seller']);
    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $shop->update($request->only([
            'name', 'description', 'logo', 'banner', 'address', 'city', 'phone'
        ]));

        $user->update(['role' => 'seller']);
    return response()->json(['success' => true, 'data' => $shop]);
    }

    // Ma boutique
    public function myShop(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            $user->update(['role' => 'seller']);
    return response()->json(['success' => false, 'message' => 'No shop found'], 404);
        }
        $user->update(['role' => 'seller']);
    return response()->json(['success' => true, 'data' => $shop]);
    }
    

public function myShopProfile(Request $request)
{
    $user = $request->user();
    $shop = $user->shop;

    if (!$shop) {
        $user->update(['role' => 'seller']);
    return response()->json(['success' => false, 'message' => 'Boutique introuvable'], 404);
    }

    // Générer les URLs complètes
    $shop->logo = $shop->logo ? asset('storage/' . $shop->logo) : null;
    $shop->banner = $shop->banner ? asset('storage/' . $shop->banner) : null;

    $user->update(['role' => 'seller']);
    return response()->json(['success' => true, 'data' => $shop]);
}
}
