<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\NotchPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('items.product')->where('user_id', $request->user()->id)->get();
        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'shipping_address'   => 'required|string',
            'payment_method'     => 'nullable|string',
            'items'              => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $total = 0;
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $total  += $product->price * $item['quantity'];
            }

            $order = Order::create([
                'user_id'          => $request->user()->id,
                'total_amount'     => $total,
                'status'           => 'pending',
                'payment_method'   => $request->payment_method,
                'shipping_address' => $request->shipping_address,
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $product->price,
                ]);
                $product->decrement('stock', $item['quantity']);
            }

            DB::commit();
            return response()->json(['success' => true, 'data' => $order], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $order = Order::with('items.product')->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $order]);
    }

    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $order->update($request->only(['status', 'payment_method']));
        return response()->json(['success' => true, 'data' => $order]);
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $order->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    public function payWithWallet(Request $request, $id)
    {
        $user  = Auth::user();
        $order = Order::where('id', $id)->where('user_id', $user->id)->first();

        if (!$order) {
            return response()->json(['message' => 'Commande non trouvée'], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Commande déjà payée ou annulée'], 400);
        }

        $wallet = $user->wallet;

        if ($wallet->balance < $order->total_amount) {
            return response()->json([
                'message'  => 'Solde insuffisant',
                'balance'  => $wallet->balance,
                'required' => $order->total_amount,
            ], 400);
        }

        DB::beginTransaction();
        try {
            $transaction = $wallet->debit($order->total_amount, "Paiement commande #{$order->id}");

            $order->update([
                'status'            => 'paid',
                'payment_method'    => 'wallet',
                'payment_reference' => $transaction->reference,
            ]);

            DB::commit();

            return response()->json([
                'message'     => 'Commande payée avec succès',
                'order'       => $order->fresh(),
                'balance'     => $wallet->fresh()->balance,
                'transaction' => $transaction,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function payWithNotchPay(Request $request, $id)
    {
        $user  = Auth::user();
        $order = Order::where('id', $id)->where('user_id', $user->id)->first();

        if (!$order) {
            return response()->json(['message' => 'Commande non trouvée'], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Commande déjà payée ou annulée'], 400);
        }

        $reference = 'ORD-' . strtoupper(uniqid());
        $notchpay  = app(NotchPayService::class);
        $wallet    = $user->wallet;

        $transaction = $wallet->transactions()->create([
            'user_id'        => $user->id,
            'reference'      => $reference,
            'type'           => 'debit',
            'status'         => 'pending',
            'amount'         => $order->total_amount,
            'balance_before' => $wallet->balance,
            'balance_after'  => $wallet->balance,
            'payment_method' => 'notchpay',
            'description'    => "Paiement commande #{$order->id}",
            'metadata'       => ['order_id' => $order->id],
        ]);

        $response = $notchpay->initiatePayment([
            'amount'       => $order->total_amount,
            'currency'     => 'XAF',
            'email'        => $user->email,
            'reference'    => $reference,
            'description'  => "Paiement commande #{$order->id} - GRO",
            'callback_url' => config('app.frontend_url') . '/orders/' . $order->id . '/callback',
        ]);

        if (!isset($response['authorization_url'])) {
            $transaction->update(['status' => 'failed']);
            return response()->json(['message' => 'Erreur Notch Pay', 'error' => $response], 400);
        }

        $order->update([
            'payment_method'    => 'notchpay',
            'payment_reference' => $reference,
        ]);

        return response()->json([
            'message'           => 'Paiement initié',
            'reference'         => $reference,
            'authorization_url' => $response['authorization_url'],
        ]);
    }
}
