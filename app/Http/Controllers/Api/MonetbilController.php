<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FundsReleasedMail;
use App\Mail\NewOrderReceivedMail;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderConfirmedMail;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Events\OrderPaid;

class MonetbilController extends Controller
{
    private $serviceKey;

    public function __construct()
    {
        $this->serviceKey = config('services.monetbil.service_key');
    }

    // ==================== PAIEMENT AVEC MONETBIL ====================
    public function payWithMonetbil(Order $order, Request $request)
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }
        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Commande déjà traitée'], 422);
        }

        try {
            $response = Http::asForm()->post('https://api.monetbil.com/payment/v1/placePayment', [
                'service'      => $this->serviceKey,
                'amount'       => (int) $order->total_amount,
                'phonenumber'  => '237' . ltrim($user->phone, '237'),
                'return_url'   => env('FRONTEND_URL') . '/payment/success?order=' . $order->order_number,
                'notify_url'   => config('app.url') . '/api/monetbil/webhook',
                'item_ref'     => $order->order_number,
            ]);

            $data = $response->json();
            Log::info('Monetbil payment initiated', $data);

            if (isset($data['status']) && $data['status'] === 'REQUEST_ACCEPTED') {
                $order->update(['payment_reference' => $data['paymentId']]);
                return response()->json([
                    'success'    => true,
                    'payment_id' => $data['paymentId'],
                    'ussd'       => $data['channel_ussd'] ?? null,
                    'channel'    => $data['channel_name'] ?? null,
                    'message'    => 'Paiement initié. Composez ' . ($data['channel_ussd'] ?? '*126#') . ' pour confirmer.',
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Erreur Monetbil', 'debug' => $data], 500);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==================== WEBHOOK MONETBIL ====================
    public function webhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Webhook Monetbil reçu', $payload);

        $itemRef   = $payload['item_ref'] ?? null;
        $status    = $payload['status'] ?? null;

        if (!$itemRef) {
            Log::error('Webhook Monetbil : référence manquante', $payload);
            return response()->json(['status' => 'missing_reference'], 400);
        }

        if ($status === 'SUCCESS') {
            $order = Order::where('order_number', $itemRef)->first();

            if ($order && $order->status !== 'paid') {
                $order->status = 'paid';
                $order->save();
                Log::info("Commande {$order->order_number} payée via Monetbil");

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

    // ==================== VÉRIFICATION MANUELLE ====================
    public function verifyPayment(Request $request)
    {
        $paymentId = $request->input('payment_id');
        $orderNumber = $request->input('order_number');

        if (!$paymentId || !$orderNumber) {
            return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 400);
        }

        try {
            $response = Http::asForm()->post('https://api.monetbil.com/payment/v1/checkPayment', [
                'service'    => $this->serviceKey,
                'payment_ref' => $paymentId,
            ]);

            $data = $response->json();
            Log::info('Monetbil verify payment', $data);

            $status = $data['status'] ?? null;

            if ($status === 'SUCCESS') {
                $order = Order::where('order_number', $orderNumber)->where('status', 'pending')->first();
                if ($order) {
                    $order->status = 'paid';
                    $order->save();

                    try {
                        $order->load(['user', 'items.product']);
                        if ($order->user && $order->user->email) {
                            Mail::to($order->user->email)->send(new OrderConfirmedMail($order));
                        }
                        if ($order->shop && $order->shop->user) {
                            Mail::to($order->shop->user->email)->send(new NewOrderReceivedMail($order));
                        }
                    } catch (\Exception $e) {
                        Log::error('Emails vérification Monetbil échoués : ' . $e->getMessage());
                    }

                    return response()->json(['success' => true, 'status' => 'paid']);
                }
            }

            return response()->json(['success' => false, 'status' => $status]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
