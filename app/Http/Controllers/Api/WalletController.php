<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\NotchPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    private function walletForUser($user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'currency' => 'XAF']
        );
    }

    private function validateWalletPin(Request $request, Wallet $wallet)
    {
        if (!$wallet->pin_hash) {
            return response()->json([
                'message' => 'Veuillez configurer votre PIN wallet',
                'pin_required' => true,
                'setup_required' => true,
            ], 428);
        }

        $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        if (!Hash::check((string) $request->input('pin'), $wallet->pin_hash)) {
            return response()->json(['message' => 'PIN wallet incorrect'], 403);
        }

        return null;
    }

    public function securityStatus(Request $request)
    {
        $wallet = $this->walletForUser($request->user());

        return response()->json([
            'has_pin' => (bool) $wallet->pin_hash,
            'pin_set_at' => $wallet->pin_set_at,
        ]);
    }

    public function setupPin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'digits:4', 'confirmed'],
        ]);

        $wallet = $this->walletForUser($request->user());

        if ($wallet->pin_hash) {
            return response()->json(['message' => 'Le PIN wallet est deja configure'], 422);
        }

        $wallet->forceFill([
            'pin_hash' => Hash::make((string) $request->pin),
            'pin_set_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'PIN wallet configure avec succes',
        ]);
    }

    public function balance(Request $request)
    {
        $wallet = $this->walletForUser($request->user());

        if ($pinResponse = $this->validateWalletPin($request, $wallet)) {
            return $pinResponse;
        }

        return response()->json([
            'balance' => $wallet->balance,
            'total_credited' => $wallet->total_credited,
            'total_debited' => $wallet->total_debited,
            'currency' => $wallet->currency,
            'user_id' => $request->user()->id,
            'has_pin' => true,
        ]);
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:notchpay,monetbil',
            'pin' => 'required|digits:4',
        ]);

        $user = $request->user();
        $wallet = $this->walletForUser($user);

        if ($pinResponse = $this->validateWalletPin($request, $wallet)) {
            return $pinResponse;
        }

        if ($request->method === 'monetbil') {
            return response()->json([
                'message' => 'Monetbil non implemente pour le moment',
            ], 501);
        }

        $amount = (float) $request->amount;
        $reference = 'DEP-' . uniqid();
        $balanceBefore = $wallet->balance;

        try {
            $notchpay = app(NotchPayService::class);
            $response = $notchpay->initiatePayment([
                'amount' => $amount,
                'currency' => 'XAF',
                'description' => 'Depot wallet AgriPulse',
                'customer_email' => $user->email,
                'customer_name' => $user->firstname . ' ' . $user->lastname,
                'customer_phone' => $user->phone ?? '',
                'reference' => $reference,
                'callback_url' => env('NOTCHPAY_CALLBACK_URL', config('app.url') . '/api/wallet/deposit/callback'),
                'return_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/wallet',
            ]);

            if (($response['error'] ?? false) === true) {
                Log::warning('NotchPay deposit error', $response);

                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Impossible d\'initier le paiement',
                ], 502);
            }

            $authorizationUrl = $response['authorization_url'] ?? null;

            if (!$authorizationUrl) {
                Log::warning('NotchPay deposit without authorization URL', $response);

                return response()->json([
                    'success' => false,
                    'message' => 'Aucune URL de paiement recue. Le compte n\'a pas ete recharge.',
                ], 502);
            }

            $transaction = Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore,
                'description' => 'Depot via NotchPay',
                'status' => 'pending',
                'reference' => $reference,
                'external_reference' => $response['transaction']['reference'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement initie avec succes',
                'data' => [
                    'reference' => $reference,
                    'amount' => $amount,
                    'status' => 'pending',
                    'authorization_url' => $authorizationUrl,
                    'transaction_id' => $transaction->id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur depot NotchPay: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'initier le paiement. Le compte n\'a pas ete recharge.',
            ], 502);
        }
    }

    public function verifyPayment(Request $request, $reference)
    {
        $user = $request->user();
        $transaction = Transaction::where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction non trouvee'], 404);
        }

        if ($transaction->status === 'completed') {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'amount' => $transaction->amount,
            ]);
        }

        try {
            $notchpay = app(NotchPayService::class);
            $response = $notchpay->verifyPayment($reference);

            if (($response['status'] ?? null) === 'completed') {
                $wallet = Wallet::find($transaction->wallet_id);
                if ($wallet) {
                    $balanceBefore = $wallet->balance;
                    $wallet->balance += $transaction->amount;
                    $wallet->save();

                    $transaction->balance_before = $balanceBefore;
                    $transaction->balance_after = $wallet->balance;
                    $transaction->status = 'completed';
                    $transaction->save();
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur verification paiement: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
        ]);
    }

    public function callback(Request $request)
    {
        Log::info('NotchPay wallet webhook received', $request->all());

        $reference = $request->input('reference');
        $status = $request->input('status');

        if ($reference && $status === 'completed') {
            $transaction = Transaction::where('reference', $reference)->first();
            if ($transaction && $transaction->status === 'pending') {
                $wallet = Wallet::find($transaction->wallet_id);
                if ($wallet) {
                    $balanceBefore = $wallet->balance;
                    $wallet->balance += $transaction->amount;
                    $wallet->save();

                    $transaction->balance_before = $balanceBefore;
                    $transaction->balance_after = $wallet->balance;
                    $transaction->status = 'completed';
                    $transaction->save();
                }
            }
        }

        return response()->json(['success' => true]);
    }

    public function depositCallback(Request $request)
    {
        return $this->callback($request);
    }

    public function history(Request $request)
    {
        $wallet = Wallet::where('user_id', $request->user()->id)->first();

        if (!$wallet) {
            return response()->json(['data' => []]);
        }

        $transactions = Transaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $transactions]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'pin' => 'required|digits:4',
        ]);

        $user = $request->user();
        $amount = (float) $request->amount;
        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet || $wallet->balance < $amount) {
            return response()->json(['message' => 'Solde insuffisant'], 422);
        }

        if ($pinResponse = $this->validateWalletPin($request, $wallet)) {
            return $pinResponse;
        }

        DB::beginTransaction();
        try {
            $balanceBefore = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->save();

            Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'withdraw',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => 'Retrait',
                'status' => 'completed',
                'reference' => 'WTH-' . uniqid(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retrait effectue avec succes',
                'balance' => $wallet->balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur retrait: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du retrait'], 500);
        }
    }

    public function transfer()
    {
        return response()->json(['message' => 'Transfert non implemente pour le moment'], 501);
    }
}
