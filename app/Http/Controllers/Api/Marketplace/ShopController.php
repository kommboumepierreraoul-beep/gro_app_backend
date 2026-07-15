<?php
namespace App\Http\Controllers\Api\Marketplace;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\CloudinaryService;
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

        return response()->json(['success' => true, 'data' => $shops]);
    }

    // Créer sa boutique
    public function store(Request $request)
{
    $user = $request->user();

    if ($user->shop) {
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
        $logoPath = app(CloudinaryService::class)
            ->uploadImageUrl($request->file('logo'), 'agripulse/shops/logos');
    }

    if ($request->hasFile('banner')) {
        $bannerPath = app(CloudinaryService::class)
            ->uploadImageUrl($request->file('banner'), 'agripulse/shops/banners');
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
        'status'      => 'pending',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Votre demande de boutique a ete envoyee. Elle sera examinee par un administrateur.',
        'data' => $shop
    ], 201);
}

    // Voir une boutique
    public function show($id)
    {
        $shop = Shop::with(['user:id,firstname,lastname', 'products' => function ($q) {
            $q->where('status', 'active')->limit(10);
        }])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $shop]);
    }

    // Modifier sa boutique
    public function update(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);

        if ($shop->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $shop->update($request->only([
            'name', 'description', 'logo', 'banner', 'address', 'city', 'phone'
        ]));

        return response()->json(['success' => true, 'data' => $shop]);
    }

    // Ma boutique
    public function myShop(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'No shop found'], 404);
        }

        return response()->json(['success' => true, 'data' => $shop]);
    }
    

public function myShopProfile(Request $request)
{
    $user = $request->user();
    $shop = $user->shop;

    if (!$shop) {
        return response()->json(['success' => false, 'message' => 'Boutique introuvable'], 404);
    }

    // Générer les URLs complètes
    $shop->logo = $shop->logo && !str_starts_with($shop->logo, 'http')
        ? asset('storage/' . $shop->logo)
        : $shop->logo;
    $shop->banner = $shop->banner && !str_starts_with($shop->banner, 'http')
        ? asset('storage/' . $shop->banner)
        : $shop->banner;

    return response()->json(['success' => true, 'data' => $shop]);
}
}
