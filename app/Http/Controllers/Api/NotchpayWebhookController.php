<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotchpayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Notch Pay Webhook reçu', ['payload' => $request->all()]);

        $event = $request->input('event') ?? $request->input('type');
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
        $reference = $data['merchant_reference'] ?? $data['trxref'] ?? null;
        if (!$reference) {
            Log::error('Notch Pay : Référence manquante dans payment.complete');
            return;
        }

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
            $wallet->balance += $transaction->amount;
            $wallet->total_credited += $transaction->amount;
            $wallet->save();

            $transaction->update([
                'status' => 'completed',
                'balance_before' => $wallet->balance - $transaction->amount,
                'balance_after' => $wallet->balance,
                'completed_at' => now(),
                'payment_method' => 'notchpay',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'notchpay_data' => $data,
                    'processed_at' => now(),
                ]),
            ]);

            Log::info('Wallet crédité via Notch Pay', [
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
                'new_balance' => $wallet->balance,
            ]);
        }

        if ($transaction->type === 'debit') {
            $wallet->balance -= $transaction->amount;
            $wallet->total_debited += $transaction->amount;
            $wallet->save();

            $transaction->update([
                'status' => 'completed',
                'balance_before' => $wallet->balance + $transaction->amount,
                'balance_after' => $wallet->balance,
                'completed_at' => now(),
                'payment_method' => 'notchpay',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'notchpay_data' => $data,
                    'processed_at' => now(),
                ]),
            ]);

            $metadata = $transaction->metadata ?? [];
            $orderId = $metadata['order_id'] ?? null;
            if ($orderId) {
                \App\Models\Order::where('id', $orderId)
                    ->where('status', 'pending')
                    ->update(['status' => 'paid']);
                Log::info('Commande payée via Notch Pay', [
                    'order_id' => $orderId,
                    'amount' => $transaction->amount,
                ]);
            }
        }
    }

    private function handlePaymentFailed(array $data): void
    {
        $reference = $data['merchant_reference'] ?? $data['trxref'] ?? null;
        if (!$reference) {
            Log::error('Notch Pay : Référence manquante dans payment.failed');
            return;
        }

        Transaction::where('reference', $reference)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        Log::warning('Paiement Notch Pay échoué', ['reference' => $reference]);
    }

    private function verifySignature(string $payload, ?string $signature, string $hashKey): bool
    {
        if (!$signature) return false;
        $expectedSignature = hash_hmac('sha256', $payload, $hashKey);
        return hash_equals($expectedSignature, $signature);
    }
}
