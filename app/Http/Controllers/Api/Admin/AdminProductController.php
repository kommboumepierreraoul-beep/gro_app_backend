<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ProductApprovedMail;
use App\Mail\ProductRejectedMail;

class AdminProductController extends Controller
{
    // Produits en attente
    public function pendingProducts()
    {
        $products = Product::with([
            'shop:id,name,logo',
            'category:id,name'
        ])
        ->where('approval_status', 'pending')
        ->orderBy('created_at', 'desc')
        ->get();

        // Convertir les logos et images en URLs
        foreach ($products as $product) {
            // Transformer le logo du shop
            if ($product->shop && $product->shop->logo) {
                if (!str_starts_with($product->shop->logo, 'http')) {
                    $product->shop->logo = asset('storage/' . $product->shop->logo);
                }
            }
            
            // Les images sont déjà en URLs complètes grâce au cast 'array'
            // Pas besoin de les transformer
        }

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

  // Approuver un produit
public function approveProduct($id)
{
    
    $product = Product::with('shop.user')->findOrFail($id);

    $product->update([
        'approval_status' => 'approved',
        'status' => 'active',
        'rejection_reason' => null
    ]);

    // Ajouter à la timeline
    ActivityLog::log(
        'product_approved',
        "Produit \"{$product->name}\" approuvé",
        'Product',
        $product->id
    );

    // Envoyer un email au propriétaire de la boutique
    if (
        $product->shop &&
        $product->shop->user &&
        $product->shop->user->email
    ) {

        Mail::to($product->shop->user->email)
            ->send(

                new ProductApprovedMail(

                    $product->shop->user->firstname
                        ?? $product->shop->user->name
                        ?? 'Utilisateur',

                    $product->name

                )

            );
    }

    return response()->json([
        'success' => true,
        'message' => 'Produit approuvé avec succès',
        'data' => $product
    ]);
}
    // Rejeter un produit
    // Rejeter un produit
public function rejectProduct(Request $request, $id)
{
    $request->validate([
        'reason' => 'nullable|string|max:500'
    ]);

    $product = Product::with('shop.user')->findOrFail($id);

    $product->update([
        'approval_status' => 'rejected',
        'status' => 'inactive',
        'rejection_reason' => $request->reason
    ]);

    // Ajouter à la timeline
    ActivityLog::log(
        'product_rejected',
        "Produit \"{$product->name}\" rejeté" .
        ($request->reason ? ": {$request->reason}" : ''),
        'Product',
        $product->id
    );

    // Envoyer un email au propriétaire de la boutique
    if (
        $product->shop &&
        $product->shop->user &&
        $product->shop->user->email
    ) {

        Mail::to($product->shop->user->email)
            ->send(

                new ProductRejectedMail(

                    $product->shop->user->firstname
                        ?? $product->shop->user->name
                        ?? 'Utilisateur',

                    $product->name,

                    $request->reason
                        ?? 'Votre produit n’a pas été validé par l’administration.'

                )

            );
    }

    return response()->json([
        'success' => true,
        'message' => 'Produit rejeté',
        'data' => $product
    ]);
}
    // Tous les produits (catalogue)
    public function allProducts(Request $request)
    {
        $limit = $request->query('limit', 20);
        $page = $request->query('page', 1);

        $products = Product::with([
            'shop:id,name,logo',
            'category:id,name'
        ])
        ->orderBy('created_at', 'desc')
        ->paginate($limit, ['*'], 'page', $page);

        // Convertir les logos
        foreach ($products as $product) {
            if ($product->shop && $product->shop->logo) {
                $product->shop->logo = asset('storage/' . $product->shop->logo);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    // Supprimer un produit
    public function deleteProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé'
        ]);
    }
}
