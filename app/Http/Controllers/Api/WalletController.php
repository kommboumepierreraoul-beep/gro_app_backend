<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotchPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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

    // ✅ Initier un dépôt via Notch Pay
    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $user      = Auth::user();
        $wallet    = $user->wallet;
        $reference = 'TRX-' . strtoupper(uniqid());

        // Créer la transaction en pending
        $transaction = $wallet->transactions()->create([
            'user_id'        => $user->id,
            'reference'      => $reference,
            'type'           => 'credit',
            'status'         => 'pending',
            'amount'         => $request->amount,
            'balance_before' => $wallet->balance,
            'balance_after'  => $wallet->balance,
            'payment_method' => 'notchpay',
            'description'    => 'Dépôt via Notch Pay',
        ]);

        // Appeler l'API Notch Pay
        $response = $this->notchpay->initiatePayment([
            'amount'       => $request->amount,
            'currency'     => $wallet->currency,
            'email'        => $user->email,
            'reference'    => $reference,
            'description'  => 'Dépôt wallet GRO',
            'callback_url' => config('app.frontend_url') . '/wallet/callback',
        ]);

         if (!isset($response['authorization_url'])) {
            $transaction->update(['status' => 'failed']);
            return response()->json([
                'message' => 'Erreur lors de l\'initiation du paiement',
                'error'   => $response
            ], 400);
        }

        return response()->json([
            'message'           => 'Paiement initié',
            'reference'         => $reference,
            'authorization_url' => $response['authorization_url'],
        ]);
    }

    // ✅ Retrait via Notch Pay Transfer
  public function withdraw(Request $request)
{
    $request->validate([
        'amount'  => 'required|numeric|min:100',
        'phone'   => 'required|string',
        'channel' => 'required|in:cm.mtn,cm.orange',
    ]);

    $user   = Auth::user();
    $wallet = $user->wallet;

    if ($wallet->balance < $request->amount) {
        return response()->json(['message' => 'Solde insuffisant'], 400);
    }

    // Débiter le wallet
    $transaction = $wallet->debit(
        $request->amount,
        'Retrait via ' . $request->channel
    );

    // Mettre à jour les métadonnées
    $transaction->update([
        'payment_method' => 'notchpay',
        'metadata'       => [
            'phone'   => $request->phone,
            'channel' => $request->channel,
            'note'    => 'Virement en cours de traitement',
        ],
    ]);

    return response()->json([
        'message'     => 'Retrait initié — virement en cours de traitement',
        'transaction' => $transaction,
        'balance'     => $wallet->fresh()->balance,
    ]);
}

    public function history()
    {
        $wallet       = Auth::user()->wallet;
        $transactions = $wallet->transactions()->orderBy('id', 'desc')->paginate(15);
        return response()->json($transactions);
 
        }

        // Vérifier un paiement
public function verifyPayment(string $reference)
{
    $user        = Auth::user();
    $transaction = $user->wallet->transactions()
                        ->where('reference', $reference)
                        ->first();

    if (!$transaction) {
        return response()->json(['message' => 'Transaction non trouvée'], 404);
    }

    // Vérifier sur Notch Pay
    $response = $this->notchpay->verifyPayment($transaction->reference);

    if (!isset($response['transaction'])) {
        return response()->json(['message' => 'Impossible de vérifier le paiement'], 400);
    }

    $status = $response['transaction']['status'];

    // Si complété mais pas encore crédité
    if ($status === 'complete' && $transaction->status === 'pending') {
        $transaction->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'metadata'     => $response['transaction'],
        ]);
        $user->wallet->credit($transaction->amount, 'Dépôt confirmé');
    }

    return response()->json([
        'transaction'   => $transaction->fresh(),
        'notchpay_status' => $status,
        'balance'       => $user->wallet->fresh()->balance,
    ]);
}

// Callback après paiement
public function callback(Request $request)
{
    $reference = $request->query('reference') ?? $request->query('trxref');

    if (!$reference) {
        return response()->json(['message' => 'Référence manquante'], 400);
    }

    $transaction = \App\Models\Transaction::where('reference', $reference)->first();

    if (!$transaction) {
        return response()->json(['message' => 'Transaction non trouvée'], 404);
    }

    // Vérifier sur Notch Pay
    $response = $this->notchpay->verifyPayment($reference);
    $status   = $response['transaction']['status'] ?? 'unknown';

    if ($status === 'complete' && $transaction->status === 'pending') {
        $wallet = $transaction->wallet;
        $transaction->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
        $wallet->credit($transaction->amount, 'Dépôt confirmé via callback');
    }

    return response()->json([
        'message'     => 'Paiement vérifié',
        'status'      => $status,
        'transaction' => $transaction->fresh(),
    ]);
}
}