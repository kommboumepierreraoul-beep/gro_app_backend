<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotchpayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Notch Pay Webhook reçu', ['payload' => $request->all()]);

        $event     = $request->input('event') ?? $request->input('type');
        $eventData = $request->input('data', []);

        // Récupération de la référence - TOUS les formats possibles
        $reference = $eventData['merchant_reference'] 
                  ?? $eventData['reference'] 
                  ?? $eventData['trxref'] 
                  ?? $request->input('reference')
                  ?? $request->input('merchant_reference')
                  ?? $request->input('trxref')
                  ?? null;

        if (!$reference) {
            Log::error('Notch Pay : Référence manquante', ['payload' => $request->all()]);
            return response()->json(['status' => 'missing_reference'], 400);
        }

        Log::info('Notch Pay : Référence trouvée', ['reference' => $reference]);

        switch ($event) {
            case 'payment.complete':
            case 'payment.success':
            case 'transaction.completed':
                $this->handlePaymentComplete($eventData, $reference);
                break;
            case 'payment.failed':
            case 'transaction.failed':
                $this->handlePaymentFailed($eventData, $reference);
                break;
            case 'payment.created':
                Log::info('Nouveau paiement créé', ['reference' => $reference]);
                break;
            default:
                Log::info('Événement webhook non géré', ['type' => $event]);
                break;
        }

        return response()->json(['status' => 'received'], 200);
    }

    private function handlePaymentComplete(array $data, string $reference): void
    {
        Log::info('handlePaymentComplete', ['reference' => $reference, 'data' => $data]);

        // ✅ CAS 1 : Paiement de commande (reference = ORD-XXXXXXXX)
        if (str_starts_with($reference, 'ORD-')) {
            $order = Order::where('order_number', $reference)->first();

            if (!$order) {
                Log::error('Notch Pay : Commande non trouvée', ['reference' => $reference]);
                return;
            }

            if ($order->status !== 'pending') {
                Log::info('Notch Pay : Commande déjà traitée', ['reference' => $reference, 'status' => $order->status]);
                return;
            }

            $order->status = 'paid';
            $order->payment_status = 'completed';
            $order->payment_reference = $data['id'] ?? $data['reference'] ?? null;
            $order->save();

            Log::info('✅ Commande payée via Notch Pay', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'amount'       => $order->total_amount,
            ]);
            return;
        }

        // ✅ CAS 2 : Dépôt wallet (reference = TRX-XXXXXXXX)
        $transaction = Transaction::where('reference', $reference)->first();

        if (!$transaction) {
            Log::error('Notch Pay : Transaction non trouvée', ['reference' => $reference]);
            return;
        }

        if ($transaction->status !== 'pending') {
            Log::info('Notch Pay : Transaction déjà traitée', ['reference' => $reference]);
            return;
        }

        $wallet = $transaction->wallet;

        if ($transaction->type === 'deposit' || $transaction->type === 'credit') {
            $wallet->balance         += $transaction->amount;
            $wallet->total_credited  += $transaction->amount;
            $wallet->save();

            $transaction->update([
                'status'         => 'completed',
                'balance_before' => $wallet->balance - $transaction->amount,
                'balance_after'  => $wallet->balance,
                'completed_at'   => now(),
                'payment_method' => 'notchpay',
                'metadata'       => array_merge($transaction->metadata ?? [], [
                    'notchpay_data' => $data,
                    'processed_at'  => now(),
                ]),
            ]);

            Log::info('✅ Wallet crédité via Notch Pay', [
                'user_id'     => $transaction->user_id,
                'amount'      => $transaction->amount,
                'new_balance' => $wallet->balance,
            ]);
        }
    }

    private function handlePaymentFailed(array $data, string $reference): void
    {
        Log::warning('❌ Paiement Notch Pay échoué', ['reference' => $reference]);

        if (str_starts_with($reference, 'ORD-')) {
            $order = Order::where('order_number', $reference)->first();
            if ($order && $order->status === 'pending') {
                $order->status = 'payment_failed';
                $order->save();
                Log::warning('Commande marquée comme paiement échoué', ['order' => $order->id]);
            }
            return;
        }

        Transaction::where('reference', $reference)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);
    }
}
