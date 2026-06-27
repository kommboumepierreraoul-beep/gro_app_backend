<?php
namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, $productId)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $existing = ProductReview::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Already reviewed'], 400);
        }

        $review = ProductReview::create([
            'user_id'    => $request->user()->id,
            'product_id' => $productId,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
        ]);

        return response()->json(['success' => true, 'data' => $review->load('user:id,firstname,lastname')], 201);
    }

    public function destroy(Request $request, $id)
    {
        $review = ProductReview::where('user_id', $request->user()->id)->findOrFail($id);
        $review->delete();
        return response()->json(['success' => true, 'message' => 'Review deleted']);
    }
}
