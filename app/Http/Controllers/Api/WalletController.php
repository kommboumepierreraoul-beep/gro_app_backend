<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\NotchPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    private NotchPayService $notchpay;

    public function __construct(NotchPayService $notchpay)
    {
        $this->notchpay = $notchpay;
    }

    public function balance()
    {
        $wallet = Auth::user()->wallet;
        return response()->json([
            'balance'        => $wallet->balance,
            'currency'       => $wallet->currency,
            'total_credited' => $wallet->total_credited,
            'total_debited'  => $wallet->total_debited,
        ]);
    }

    public function deposit(Request $request)
    {
        $user   = $request->user();
        $amount = $request->input('amount');
        $method = $request->input('method', 'notchpay');

        if ($method !== 'notchpay') {
            return response()->json(['message' => 'Méthode non supportée'], 400);
        }

        $transaction = WalletTransaction::create([
            'user_id'     => $user->id,
            'amount'      => $amount,
            'type'        => 'credit',
            'description' => 'Dépôt via NotchPay',
            'status'      => 'pending',
        ]);

        $result = $this->notchpay->initiatePayment([
            'amount'       => $amount,
            'currency'     => 'XAF',
            'email'        => $user->email,
            'reference'    => 'deposit_' . $transaction->id,
            'description'  => 'Dépôt wallet - Transaction #' . $transaction->id,
            'callback_url' => url('/api/wallet/deposit/callback'),
        ]);

        if (!isset($result['authorization_url'])) {
            $transaction->update(['status' => 'failed']);
            Log::error('NotchPay init failed', $result);
            return response()->json(['message' => "Erreur d'initialisation du paiement"], 500);
        }

        $transaction->update(['reference' => $result['transaction']['reference'] ?? null]);

        return response()->json(['authorization_url' => $result['authorization_url']]);
    }

    public function depositCallback(Request $request)
    {
        $params = $request->isMethod('get') ? $request->query() : $request->all();
        Log::info('Deposit callback received', $params);

        $merchantRef = $params['merchant_reference'] ?? $params['trxref'] ?? $params['reference'] ?? null;
        if (!$merchantRef || !str_starts_with($merchantRef, 'deposit_')) {
            return response()->json(['status' => 'invalid_reference'], 400);
        }

        $transactionId = (int) str_replace('deposit_', '', $merchantRef);
        $transaction   = WalletTransaction::find($transactionId);
        if (!$transaction) return response()->json(['status' => 'not_found'], 404);
        if ($transaction->status !== 'pending') return response()->json(['status' => 'already_processed'], 200);

        $notchpayRef = $params['reference'] ?? $params['trxref'] ?? null;
        if (!$notchpayRef) {
            $transaction->update(['status' => 'failed']);
            return response()->json(['status' => 'missing_ref'], 400);
        }

        $verification = $this->notchpay->verifyPayment($notchpayRef);
        $realStatus   = $verification['transaction']['status'] ?? null;

        Log::info('NotchPay verification', ['realStatus' => $realStatus, 'full' => $verification]);

        if ($realStatus === 'complete') {
            $transaction->update(['status' => 'completed']);
            $transaction->user->wallet->credit($transaction->amount, $transaction->description, [], $transaction);
            Log::info('Deposit completed', ['tx_id' => $transactionId]);
        } else {
            $transaction->update(['status' => 'failed']);
            Log::warning('Deposit failed', ['tx_id' => $transactionId, 'status' => $realStatus]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount'  => 'required|numeric|min:100',
            'phone'   => 'required|string',
            'channel' => 'required|in:mtn,orange',
        ]);

        $user   = Auth::user();
        $wallet = $user->wallet;

        if ($wallet->balance < $request->amount) {
            return response()->json(['message' => 'Échec – solde insuffisant'], 400);
        }

        $transaction = WalletTransaction::create([
            'user_id'     => $user->id,
            'amount'      => $request->amount,
            'type'        => 'debit',
            'description' => 'Retrait via ' . $request->channel,
            'status'      => 'pending',
        ]);

        if (config('services.notchpay.sandbox') || app()->environment('local')) {
            $wallet->debit($request->amount, $transaction->description);
            $transaction->update([
                'status'    => 'completed',
                'reference' => 'simul_' . uniqid(),
            ]);
            return response()->json([
                'message' => 'Retrait effectué (sandbox)',
                'balance' => $wallet->fresh()->balance,
            ]);
        }

        $result = $this->notchpay->initiateTransfer([
            'amount'      => $request->amount,
            'currency'    => 'XAF',
            'phone'       => $request->phone,
            'channel'     => $request->channel === 'mtn' ? 'cm.mtn' : 'cm.orange',
            'reference'   => 'withdraw_' . $transaction->id,
            'description' => 'Retrait wallet - Transaction #' . $transaction->id,
        ]);

        if (isset($result['status']) && $result['status'] === 'success') {
            $wallet->debit($request->amount, $transaction->description);
            $transaction->update(['status' => 'completed', 'reference' => $result['reference'] ?? null]);
            return response()->json(['message' => 'Retrait effectué', 'balance' => $wallet->fresh()->balance]);
        } elseif (isset($result['status']) && $result['status'] === 'pending') {
            $wallet->debit($request->amount, $transaction->description);
            $transaction->update(['status' => 'processing', 'reference' => $result['reference'] ?? null]);
            return response()->json(['message' => 'Retrait en cours', 'balance' => $wallet->fresh()->balance]);
        } else {
            $transaction->update(['status' => 'failed']);
            Log::error('Withdraw failed', $result);
            return response()->json(['message' => 'Erreur lors du retrait'], 500);
        }
    }

    public function history()
    {
        $user = Auth::user();
        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return response()->json($transactions);
    }

    public function verifyPayment(string $reference)
    {
        $transaction = WalletTransaction::where('reference', $reference)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaction non trouvée'], 404);
        }

        $result = $this->notchpay->verifyPayment($reference);
        $status = $result['transaction']['status'] ?? null;

        if ($status === 'complete' && $transaction->status === 'pending') {
            $transaction->update(['status' => 'completed']);
            $transaction->user->wallet->credit($transaction->amount, $transaction->description, [], $transaction);
        }

        return response()->json([
            'transaction'     => $transaction->fresh(),
            'notchpay_status' => $status,
            'balance'         => $transaction->user->wallet->balance,
        ]);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');
        return $this->verifyPayment($reference);
    }
}
