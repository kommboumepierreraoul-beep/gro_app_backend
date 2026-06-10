<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlist = Wishlist::with('product')->where('user_id', $request->user()->id)->get();
        return response()->json(['success' => true, 'data' => $wishlist]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $exists = Wishlist::where('user_id', $request->user()->id)->where('product_id', $request->product_id)->first();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Already in wishlist'], 409);
        }

        $wishlist = Wishlist::create([
            'user_id' => $request->user()->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json(['success' => true, 'data' => $wishlist], 201);
    }

    public function destroy(Request $request, $id)
    {
        $item = Wishlist::where('user_id', $request->user()->id)->where('product_id', $id)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $item->delete();
        return response()->json(['success' => true, 'message' => 'Removed from wishlist']);
    }
}
