<?php
namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // Mes commandes
    public function index(Request $request)
    {
        $orders = Order::with('items.product:id,name,images')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // Passer une commande
    public function store(Request $request)
    {
        $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'shipping_address' => 'required|string',
            'payment_method'   => 'nullable|string',
        ]);

        $total = 0;
        $orderItems = [];

        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->stock < $item['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock insuffisant pour : {$product->name}"
                ], 400);
            }

            $total += $product->price * $item['quantity'];
            $orderItems[] = [
                'product_id' => $product->id,
                'quantity'   => $item['quantity'],
                'price'      => $product->price,
            ];

            // Réduire le stock
            $product->decrement('stock', $item['quantity']);
        }

        $order = Order::create([
            'user_id'          => $request->user()->id,
            'total_amount'     => $total,
            'status'           => 'pending',
            'payment_method'   => $request->payment_method,
            'shipping_address' => $request->shipping_address,
        ]);

        $order->items()->createMany($orderItems);

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'data'    => $order->load('items.product:id,name,images,price'),
        ], 201);
    }

    // Voir une commande
    public function show(Request $request, $id)
    {
        $order = Order::with('items.product:id,name,images,price')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $order]);
    }

    // Annuler une commande
    public function cancel(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($order->status, ['pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be cancelled'
            ], 400);
        }

        // Remettre le stock
        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Order cancelled']);
    }
}
