<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FundsReleasedMail;
use App\Mail\NewOrderReceivedMail;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Events\OrderPaid;

class OrderController extends Controller
{
    use AuthorizesRequests;

    // ==================== STORE ====================
    public function store(Request $request)
    {
        $user = $request->user();
        $cartItems = $request->input('items', []);
        if (empty($cartItems)) {
            return response()->json(['success' => false, 'message' => 'Panier vide'], 400);
        }

        $shopId = $request->input('shop_id');
        if (!$shopId && !empty($cartItems)) {
            $firstProduct = \App\Models\Product::find($cartItems[0]['product_id']);
            if ($firstProduct) $shopId = $firstProduct->shop_id;
        }

        $total = 0;
        $orderItemsData = [];

        foreach ($cartItems as $item) {
            $product = \App\Models\Product::find($item['product_id']);
            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Produit introuvable'], 422);
            }
            $price = $product->price;
            $total += $item['quantity'] * $price;
            $orderItemsData[] = [
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'unit_price' => $price,
            ];
        }

        $order = Order::create([
            'user_id'          => $user->id,
            'shop_id'          => $shopId,
            'order_number'     => 'ORD-' . Str::random(8),
            'total_amount'     => $total,
            'shipping_address' => $request->shipping_address,
            'status'           => 'pending',
        ]);

        foreach ($orderItemsData as $data) {
            OrderItem::create(array_merge($data, ['order_id' => $order->id]));
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    // ==================== PRÉPARER (vendeur) ====================
    public function prepareOrder(Order $order)
    {
        // Vérifier que la commande a une boutique associée
        if (!$order->shop) {
            Log::error('prepareOrder: commande sans boutique', ['order_id' => $order->id]);
            return response()->json(['success' => false, 'message' => 'Boutique introuvable'], 422);
        }

        Log::info('prepareOrder appelée', [
            'order_id'      => $order->id,
            'order_status'  => $order->status,
            'user_id'       => auth()->id(),
            'shop_user_id'  => $order->shop->user_id,
        ]);

        $this->authorize('update', $order);
        if ($order->status !== 'paid') {
            Log::warning('prepareOrder refusée', ['status_reçu' => $order->status]);
            return response()->json(['success' => false, 'message' => 'Commande non payée'], 422);
        }
        $order->update(['status' => 'preparing']);
        return response()->json(['success' => true, 'data' => $order]);
    }

    // ==================== EXPÉDIER (vendeur) ====================
    public function shipOrder(Order $order)
    {
        if (!$order->shop) {
            Log::error('shipOrder: commande sans boutique', ['order_id' => $order->id]);
            return response()->json(['success' => false, 'message' => 'Boutique introuvable'], 422);
        }

        $this->authorize('update', $order);
        if ($order->status !== 'preparing') {
            return response()->json(['success' => false, 'message' => 'Commande non prête'], 422);
        }
        $order->update(['status' => 'shipping']);

        // Email au client
        try {
            $order->load(['user', 'items.product']);
            if ($order->user && $order->user->email) {
                Mail::to($order->user->email)->send(new OrderShippedMail($order));
                Log::info("Email OrderShipped envoyé à {$order->user->email}");
            }
        } catch (\Exception $e) {
            Log::error('Email OrderShipped échoué : ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    // ==================== CONFIRMATION LIVRAISON (client ou vendeur) ====================
    public function confirmDelivery(Order $order, Request $request)
    {
        $user = $request->user();
        $isClient = ($user->id === $order->user_id);
        $isSeller = ($order->shop && $user->id === $order->shop->user_id);

        if (!$isClient && !$isSeller) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($isSeller) $order->seller_confirmed_delivery = true;
        if ($isClient) $order->client_confirmed_delivery = true;
        $order->save();

        if ($order->status !== 'delivered' && ($order->seller_confirmed_delivery || $order->client_confirmed_delivery)) {
            $order->status = 'delivered';
            $order->save();
        }

        if ($order->seller_confirmed_delivery && $order->client_confirmed_delivery) {
            $order->status = 'completed';
            $order->save();
            $this->releaseFundsToSeller($order);
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    // ==================== LIBÉRATION DES FONDS ====================
    private function releaseFundsToSeller(Order $order)
    {
        // Vérifications de sécurité
        if (!$order->shop || !$order->shop->user) {
            Log::error('releaseFundsToSeller: boutique ou vendeur introuvable', ['order_id' => $order->id]);
            return;
        }

        $already = WalletTransaction::where('order_id', $order->id)->where('type', 'credit')->exists();
        if ($already) return;

        DB::transaction(function () use ($order) {
            $seller = $order->shop->user;
            $wallet = $seller->wallet;
            if (!$wallet) {
                $wallet = \App\Models\Wallet::create([
                    'user_id'        => $seller->id,
                    'balance'        => 0,
                    'total_credited' => 0,
                    'total_debited'  => 0,
                    'currency'       => 'XAF',
                ]);
            }
            $wallet->credit($order->total_amount, 'Vente commande ' . $order->order_number);
            WalletTransaction::create([
    'user_id'      => $seller->id,
    'order_id'     => $order->id,
    'order_number' => $order->order_number,
    'product_name' => $order->items->first()?->product?->name ?? 'Produit',
    'amount'       => $order->total_amount,
    'type'         => 'credit',
    'description'  => 'Vente commande ' . $order->order_number,
    'status'       => 'completed',
]);

            // Email au vendeur
            try {
                $order->load(['items.product', 'user', 'shop.user']);
                Mail::to($seller->email)->send(new FundsReleasedMail($order));
                Log::info("Email FundsReleased envoyé à {$seller->email} pour la commande {$order->order_number}");
            } catch (\Exception $e) {
                Log::error('Email FundsReleased échoué : ' . $e->getMessage());
            }

            // Email au client
            try {
                if ($order->user && $order->user->email) {
                    Mail::to($order->user->email)->send(new OrderCompletedMail($order));
                    Log::info("Email OrderCompleted envoyé à {$order->user->email}");
                }
            } catch (\Exception $e) {
                Log::error('Email OrderCompleted échoué : ' . $e->getMessage());
            }
        });
    }

    // ==================== WEBHOOK NOTCHPAY ====================
    public function handleNotchPayWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Webhook NotchPay reçu (brut)', $payload);

        $data = $payload['data'] ?? [];
        $event = $payload['event'] ?? '';
        $merchantReference = $data['merchant_reference'] ?? $data['trxref'] ?? null;
        $status = $data['status'] ?? null;

        if (!$merchantReference) {
            Log::error('Webhook NotchPay : référence manquante', $payload);
            return response()->json(['status' => 'missing_reference'], 400);
        }

        if ($event === 'payment.complete' && $status === 'complete') {
            $order = Order::where('order_number', $merchantReference)->first();
            if ($order && $order->status !== 'paid') {
                $order->status = 'paid';
                $order->save();
                Log::info("Commande {$order->order_number} payée via webhook");

                // Email au client
                try {
                    $order->load(['user', 'items.product']);
                    if ($order->user && $order->user->email) {
                        Mail::to($order->user->email)->send(new OrderConfirmedMail($order));
                        Log::info("Email OrderConfirmed envoyé à {$order->user->email}");
                    }
                } catch (\Exception $e) {
                    Log::error('Email OrderConfirmed échoué : ' . $e->getMessage());
                }

                // Email au vendeur
                try {
                    if ($order->shop && $order->shop->user) {
                        $seller = $order->shop->user;
                        Mail::to($seller->email)->send(new NewOrderReceivedMail($order));
                        Log::info("Email NewOrderReceived envoyé à {$seller->email}");
                    }
                } catch (\Exception $e) {
                    Log::error('Email NewOrderReceived échoué : ' . $e->getMessage());
                }

                event(new OrderPaid($order));
            }
        }

        return response()->json(['status' => 'ok']);
    }

    // ==================== PAIEMENT AVEC WALLET ====================
    public function payWithWallet(Order $order, Request $request)
    {
        $user = $request->user();
        if ($order->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }
        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Commande déjà traitée'], 422);
        }

        $wallet = $user->wallet;
        if (!$wallet || $wallet->balance < $order->total_amount) {
            return response()->json(['success' => false, 'message' => 'Solde insuffisant'], 422);
        }

        DB::transaction(function () use ($order, $wallet) {
            $wallet->debit($order->total_amount, 'Paiement commande ' . $order->order_number);
            $order->update(['status' => 'paid']);
            event(new OrderPaid($order));
        });

        // Email au client
        try {
            $order->load(['user', 'items.product']);
            if ($order->user && $order->user->email) {
                Mail::to($order->user->email)->send(new OrderConfirmedMail($order));
                Log::info("Email OrderConfirmed envoyé à {$order->user->email}");
            }
        } catch (\Exception $e) {
            Log::error('Email OrderConfirmed échoué : ' . $e->getMessage());
        }

        // Email au vendeur
        try {
            if ($order->shop && $order->shop->user) {
                $seller = $order->shop->user;
                Mail::to($seller->email)->send(new NewOrderReceivedMail($order));
                Log::info("Email NewOrderReceived envoyé à {$seller->email}");
            }
        } catch (\Exception $e) {
            Log::error('Email NewOrderReceived échoué : ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Paiement effectué']);
    }

    // ==================== PAIEMENT AVEC NOTCHPAY ====================
    public function payWithNotchPay(Order $order, Request $request)

    {

        $user = $request->user();

        if ($order->user_id !== $user->id) {

            return response()->json(["success" => false, "message" => "Non autorisé"], 403);

        }

        if ($order->status !== "pending") {

            return response()->json(["success" => false, "message" => "Commande déjà traitée"], 422);

        }



        try {

            $response = Http::withHeaders([

                "Authorization" => config("notchpay.public_key"),

                "Content-Type"  => "application/json",

                "Accept"        => "application/json",

            ])->post("https://api.notchpay.co/payments", [

                "amount"      => (int) $order->total_amount,

                "currency"    => "XAF",

                "customer"    => [

                    "email" => $user->email,

                    "name"  => $user->name ?? $user->firstname ?? "Client",

                ],

                "reference"   => $order->order_number,

                "callback"    => config("app.url") . "/api/orders/notchpay/webhook",

                "description" => "Paiement commande " . $order->order_number,

            ]);



            $data = $response->json();

            Log::info("NotchPay response", ["data" => $data]);



            $authUrl = $data["authorization_url"] ?? $data["redirect_url"] ?? null;

            if (!$authUrl) {

                return response()->json(["success" => false, "message" => "Erreur NotchPay", "debug" => $data], 500);

            }



            $order->payment_reference = $data["reference"] ?? $data["id"] ?? null;

            $order->save();



            return response()->json([

                "success" => true,

                "authorization_url" => $authUrl,

                "reference" => $data["reference"] ?? $data["id"] ?? null,

                "order_number" => $order->order_number,

            ]);

        } catch (Exception $e) {

            Log::error("Erreur NotchPay: " . $e->getMessage());

            return response()->json(["success" => false, "message" => "Erreur NotchPay", "debug" => $e->getMessage()], 500);

        }

    }

    // ==================== VÉRIFICATION MANUELLE ====================
    public function verifyAndConfirmPayment(Request $request)
    {
        $reference = $request->input('reference');
        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'Référence manquante'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => config('services.notchpay.public_key'),
                'Accept'        => 'application/json',
            ])->get('https://api.notchpay.co/payments/' . $reference);

            $data = $response->json();
            $status = $data['transaction']['status'] ?? null;
            $merchantRef = $data['transaction']['merchant_reference'] ?? null;

            if ($status === 'complete' && $merchantRef) {
                $order = Order::where('order_number', $merchantRef)->where('status', 'pending')->first();
                if ($order) {
                    $order->status = 'paid';
                    $order->save();

                    // Emails
                    try {
                        $order->load(['user', 'items.product']);
                        if ($order->user && $order->user->email) {
                            Mail::to($order->user->email)->send(new OrderConfirmedMail($order));
                        }
                        if ($order->shop && $order->shop->user) {
                            Mail::to($order->shop->user->email)->send(new NewOrderReceivedMail($order));
                        }
                    } catch (\Exception $e) {
                        Log::error('Emails dans verifyAndConfirmPayment échoués : ' . $e->getMessage());
                    }

                    return response()->json(['success' => true, 'status' => 'paid']);
                }
            }

            return response()->json(['success' => false, 'status' => $status]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==================== ANNULATION ====================
    public function cancelOrder(Order $order, Request $request)
    {
        $user = $request->user();
        $isOwner = ($order->user_id === $user->id);
        $isSeller = ($order->shop && $order->shop->user_id === $user->id);

        if (!$isOwner && !$isSeller) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }
        if (!in_array($order->status, ['pending', 'paid'])) {
            return response()->json(['success' => false, 'message' => 'Impossible d\'annuler cette commande'], 422);
        }
        $order->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'Commande annulée']);
    }

    // ==================== MES COMMANDES (client) ====================
    public function myOrders(Request $request)
    {
        try {
            $orders = Order::with('items.product')
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['success' => true, 'data' => $orders]);
        } catch (\Exception $e) {
            Log::error('Erreur myOrders : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur interne'], 500);
        }
    }


    public function show(Request $request, Order $order)
    {
        try {
            if ($order->user_id !== $request->user()->id) {
                return response()->json(["success" => false, "message" => "Accès refusé"], 403);
            }
            $order->load(["items.product", "shop"]);
            return response()->json(["success" => true, "data" => $order]);
        } catch (\Exception $e) {
            Log::error("Erreur show order : " . $e->getMessage());
            return response()->json(["success" => false, "message" => "Erreur interne"], 500);
        }
    }

    // ==================== COMMANDES VENDEUR ====================
    public function sellerOrders(Request $request)
    {
        try {
            $shop = $request->user()->shop;
            if (!$shop) {
                return response()->json(['success' => false, 'message' => 'Aucune boutique trouvée'], 404);
            }
            $orders = Order::with('user', 'items.product')
                ->where('shop_id', $shop->id)
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['success' => true, 'data' => $orders]);
        } catch (\Exception $e) {
            Log::error('Erreur sellerOrders : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur interne'], 500);
        }
    }


    public function adminOrders()
{
    $user = auth()->user();
    // Vérifier que l'utilisateur est administrateur (vous pouvez adapter le rôle)
    if (!$user || !$user->isAdmin()) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    $orders = Order::with(['user', 'items.product', 'shop.user'])
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json(['data' => $orders]);
}
public function trackOrder($orderNumber, Request $request)
{
    $email = $request->input('email');
    if (!$email) {
        return response()->json(['message' => 'Email requis'], 400);
    }

    $order = Order::where('order_number', $orderNumber)
                  ->with(['items.product', 'user', 'shop.user'])
                  ->first();

    if (!$order || $order->user->email !== $email) {
        return response()->json(['message' => 'Commande non trouvée ou email incorrect'], 404);
    }

    // Définir les étapes de suivi (ordre)
    $steps = [
        ['status' => 'pending', 'label' => 'Commande enregistrée', 'icon' => '📝'],
        ['status' => 'paid', 'label' => 'Paiement confirmé', 'icon' => '💰'],
        ['status' => 'preparing', 'label' => 'Préparation en cours', 'icon' => '⚙️'],
        ['status' => 'shipping', 'label' => 'Expédié', 'icon' => '🚚'],
        ['status' => 'delivered', 'label' => 'Livré (en attente confirmation)', 'icon' => '📦'],
        ['status' => 'completed', 'label' => 'Finalisée', 'icon' => '✅'],
    ];

    // Trouver l'étape actuelle
    $currentStep = 0;
    foreach ($steps as $index => $step) {
        if ($order->status === $step['status']) {
            $currentStep = $index;
            break;
        }
    }

    return response()->json([
        'order_number' => $order->order_number,
        'status' => $order->status,
        'current_step' => $currentStep,
        'steps' => $steps,
        'total_amount' => $order->total_amount,
        'shipping_address' => $order->shipping_address,
        'customer_name' => $order->user->name ?? $order->user->email,
        'items' => $order->items->map(fn($item) => [
            'product_name' => $item->product->name ?? 'Produit',
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ]),
        'shop_name' => $order->shop->name ?? 'Boutique',
    ]);
}
public function getTrackingData($orderNumber, Request $request)
{
    $email = $request->input('email');
    $order = Order::where('order_number', $orderNumber)->with('user')->first();
    if (!$order || $order->user->email !== $email) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    // Géocodage de l'adresse de livraison (pour obtenir lat/lng du client)
    $clientLocation = $this->geocodeAddress($order->shipping_address);
    // Position du livreur (stockée en base ou par défaut)
    $deliveryLocation = [
        'lat' => $order->delivery_latitude ?? 3.8480,   // Valeur par défaut (Yaoundé)
        'lng' => $order->delivery_longitude ?? 11.5021,
    ];

    return response()->json([
        'client' => $clientLocation,
        'delivery' => $deliveryLocation,
        'status' => $order->status,
    ]);
}

// Fonction utilitaire de géocodage (simulé, à améliorer avec une API comme OpenStreetMap Nominatim)
private function geocodeAddress($address)
{
    $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($address) . "&format=json&limit=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'AgriApp/1.0');
    $json = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($json, true);
    if (!empty($data)) {
        return ['lat' => (float)$data[0]['lat'], 'lng' => (float)$data[0]['lon']];
    }
    return ['lat' => 3.8480, 'lng' => 11.5021]; // Valeur par défaut (Yaoundé)
}
public function updateDeliveryPosition(Request $request, $orderId)
{
    $order = Order::findOrFail($orderId);
    $this->authorize('update', $order); // Vérifie que l'utilisateur est le vendeur
    $request->validate([
        'lat' => 'required|numeric|between:-90,90',
        'lng' => 'required|numeric|between:-180,180',
    ]);
    $order->delivery_latitude = $request->lat;
    $order->delivery_longitude = $request->lng;
    $order->save();
    return response()->json(['success' => true]);
}

}