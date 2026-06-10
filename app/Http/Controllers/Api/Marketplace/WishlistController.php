<?php
namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlist = Wishlist::with('product:id,name,price,images,status')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json(['success' => true, 'data' => $wishlist]);
    }

    public function toggle(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'message' => 'Removed from wishlist', 'wishlisted' => false]);
        }

        Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $request->product_id]);
        return response()->json(['success' => true, 'message' => 'Added to wishlist', 'wishlisted' => true]);
    }
}
