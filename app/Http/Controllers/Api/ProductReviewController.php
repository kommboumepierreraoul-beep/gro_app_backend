<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = ProductReview::with('user')->where('product_id', $request->product_id)->get();
        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $review = ProductReview::create($request->all());
        return response()->json(['success' => true, 'data' => $review], 201);
    }

    public function update(Request $request, $id)
    {
        $review = ProductReview::find($id);
        if (!$review) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $review->update($request->only(['rating', 'comment']));
        return response()->json(['success' => true, 'data' => $review]);
    }

    public function destroy($id)
    {
        $review = ProductReview::find($id);
        if (!$review) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $review->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
