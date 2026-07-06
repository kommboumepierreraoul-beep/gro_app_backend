<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Mail\NewOrderReceivedMail;
use App\Mail\OrderConfirmedMail;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\NotchPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotchpayWebhookController extends Controller
{
    public function __construct(private readonly NotchPayService $notchPay) {}

    public function handle(Request $request)
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-NotchPay-Signature')
            ?? $request->header('x-notchpay-signature')
            ?? $request->header('NotchPay-Signature')
            ?? '';

        if (!$this->notchPay->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('NotchPay webhook signature invalid', [
                'signature_present' => $signature !== '',
            ]);

            return response()->json(['status' => 'invalid_signature'], 401);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? $payload['type'] ?? null;
        $data = $payload['data'] ?? $payload['transaction'] ?? $payload;
        $reference = $this->notchPay->extractReference($payload);
        $status = $this->notchPay->normalizeStatus($data['status'] ?? $payload['status'] ?? null);

        Log::info('NotchPay webhook received', [
            'event' => $event,
            'status' => $status,
            'reference' => $reference,
        ]);

        if (!$reference) {
            return response()->json(['status' => 'missing_reference'], 400);
        }

        if ($this->isSuccessEvent($event) || $this->notchPay->isSuccessfulStatus($status)) {
            $this->handlePaymentComplete($data, $reference);
            return response()->json(['status' => 'received'], 200);
        }

        if ($this->isFailureEvent($event) || $this->notchPay->isFailedStatus($status)) {
            $this->handlePaymentFailed($reference);
            return response()->json(['status' => 'received'], 200);
        }

        return response()->json(['status' => 'ignored'], 200);
    }

    private function handlePaymentComplete(array $data, string $reference): void
    {
        if ($this->confirmOrderPayment($data, $reference)) {
            return;
        }

        $this->confirmWalletDeposit($data, $reference);
    }

    private function confirmOrderPayment(array $data, string $reference): bool
    {
        $order = Order::where('order_number', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();

        if (!$order) {
            return false;
        }

        DB::transaction(function () use ($order, $data, $reference) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if (!$lockedOrder || $lockedOrder->status !== 'pending') {
                return;
            }

            $lockedOrder->status = 'paid';
            $lockedOrder->payment_status = 'completed';
            $lockedOrder->payment_method = 'notchpay';
            $lockedOrder->payment_reference = $data['id'] ?? $data['reference'] ?? $reference;
            $lockedOrder->save();

            event(new OrderPaid($lockedOrder));

            $this->sendOrderPaidEmails($lockedOrder);
        });

        Log::info('Order paid via NotchPay', [
            'order_number' => $order->order_number,
            'reference' => $reference,
        ]);

        return true;
    }

    private function confirmWalletDeposit(array $data, string $reference): void
    {
        DB::transaction(function () use ($data, $reference) {
            $transaction = Transaction::where('reference', $reference)
                ->orWhere('external_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$transaction || $transaction->status !== 'pending') {
                return;
            }

            $wallet = Wallet::whereKey($transaction->wallet_id)->lockForUpdate()->first();

            if (!$wallet) {
                Log::error('NotchPay wallet deposit without wallet', [
                    'transaction_id' => $transaction->id,
                    'reference' => $reference,
                ]);
                return;
            }

            $balanceBefore = (float) $wallet->balance;
            $wallet->balance = $balanceBefore + (float) $transaction->amount;
            $wallet->total_credited = (float) $wallet->total_credited + (float) $transaction->amount;
            $wallet->save();

            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $transaction->update([
                'status' => 'completed',
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'external_reference' => $data['id'] ?? $data['reference'] ?? $reference,
                'metadata' => array_merge($metadata, [
                    'notchpay_data' => $data,
                    'processed_at' => now()->toISOString(),
                ]),
            ]);

            Log::info('Wallet credited via NotchPay', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
            ]);
        });
    }

    private function handlePaymentFailed(string $reference): void
    {
        $order = Order::where('order_number', $reference)
            ->orWhere('payment_reference', $reference)
            ->where('status', 'pending')
            ->first();

        if ($order) {
            $order->update([
                'status' => 'payment_failed',
                'payment_status' => 'failed',
            ]);
            return;
        }

        Transaction::where(function ($query) use ($reference) {
            $query->where('reference', $reference)
                ->orWhere('external_reference', $reference);
        })
            ->where('status', 'pending')
            ->update(['status' => 'failed']);
    }

    private function sendOrderPaidEmails(Order $order): void
    {
        try {
            $order->load(['user', 'items.product', 'shop.user']);

            if ($order->user?->email) {
                Mail::to($order->user->email)->send(new OrderConfirmedMail($order));
            }

            if ($order->shop?->user?->email) {
                Mail::to($order->shop->user->email)->send(new NewOrderReceivedMail($order));
            }
        } catch (\Throwable $error) {
            Log::error('NotchPay order email failed', [
                'order_id' => $order->id,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function isSuccessEvent(?string $event): bool
    {
        return in_array($event, [
            'payment.complete',
            'payment.completed',
            'payment.success',
            'transaction.completed',
            'transaction.success',
        ], true);
    }

    private function isFailureEvent(?string $event): bool
    {
        return in_array($event, [
            'payment.failed',
            'transaction.failed',
            'payment.cancelled',
            'payment.canceled',
        ], true);
    }
}
