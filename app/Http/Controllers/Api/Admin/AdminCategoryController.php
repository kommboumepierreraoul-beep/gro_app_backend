<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    // Récupérer toutes les catégories
    public function index()
    {
        $categories = Category::all();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    // Créer une catégorie
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($request->only(['name', 'description']));

        return response()->json([
            'success' => true,
            'data' => $category
        ], 201);
    }

    // Mettre à jour une catégorie
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
        ]);

        $category = Category::findOrFail($id);
        $category->update($request->only(['name', 'description']));

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    // Supprimer une catégorie
    public function destroy($id)
    {
        Category::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catégorie supprimée'
        ]);
    }
}
