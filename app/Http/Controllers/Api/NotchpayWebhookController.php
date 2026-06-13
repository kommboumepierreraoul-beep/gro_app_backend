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

        switch ($event) {
            case 'payment.complete':
                $this->handlePaymentComplete($eventData);
                break;
            case 'payment.failed':
                $this->handlePaymentFailed($eventData);
                break;
            case 'payment.created':
                Log::info('Nouveau paiement créé', ['reference' => $eventData['reference'] ?? '']);
                break;
            default:
                Log::info('Événement webhook non géré', ['type' => $event]);
                break;
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }

    private function handlePaymentComplete(array $data): void
    {
        $reference = $data['merchant_reference'] ?? $data['reference'] ?? $data['trxref'] ?? null;

        Log::info('handlePaymentComplete', ['reference' => $reference, 'data' => $data]);

        if (!$reference) {
            Log::error('Notch Pay : Référence manquante dans payment.complete');
            return;
        }

        // ✅ CAS 1 : C'est un paiement de commande (reference = ORD-XXXXXXXX)
        if (str_starts_with($reference, 'ORD-')) {
            $order = Order::where('order_number', $reference)->first();

            if (!$order) {
                Log::error('Notch Pay : Commande non trouvée', ['reference' => $reference]);
                return;
            }

            if ($order->status !== 'pending') {
                Log::info('Notch Pay : Commande déjà traitée', ['reference' => $reference]);
                return;
            }

            $order->update(['status' => 'paid']);

            Log::info('Commande payée via Notch Pay', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'amount'       => $order->total_amount,
            ]);

            return;
        }

        // ✅ CAS 2 : C'est un dépôt wallet (reference = TRX-XXXXXXXX)
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

        if ($transaction->type === 'credit') {
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

            Log::info('Wallet crédité via Notch Pay', [
                'user_id'     => $transaction->user_id,
                'amount'      => $transaction->amount,
                'new_balance' => $wallet->balance,
            ]);
        }
    }

    private function handlePaymentFailed(array $data): void
    {
        $reference = $data['merchant_reference'] ?? $data['reference'] ?? $data['trxref'] ?? null;

        if (!$reference) {
            Log::error('Notch Pay : Référence manquante dans payment.failed');
            return;
        }

        // Commande échouée
        if (str_starts_with($reference, 'ORD-')) {
            Log::warning('Paiement commande échoué', ['reference' => $reference]);
            return;
        }

        // Transaction wallet échouée
        Transaction::where('reference', $reference)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        Log::warning('Paiement Notch Pay échoué', ['reference' => $reference]);
    }
}
