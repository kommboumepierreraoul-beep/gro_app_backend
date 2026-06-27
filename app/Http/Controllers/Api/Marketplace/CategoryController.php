<?php
namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->get();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|string',
        ]);

        $category = Category::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'image'       => $request->image,
        ]);

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function show($id)
    {
        $category = Category::withCount('products')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $category]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|string',
        ]);

        $category->update([
            'name'        => $request->name ?? $category->name,
            'slug'        => $request->name ? Str::slug($request->name) : $category->slug,
            'description' => $request->description,
            'image'       => $request->image,
        ]);

        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy($id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Category deleted']);
    }
}
