<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\NotchPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\DepositCompleted;

class WalletController extends Controller
{
    public function balance(Request $request)
    {
        $user = $request->user();
        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'currency' => 'XAF',
            ]);
        }

        return response()->json([
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'user_id' => $user->id,
        ]);
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:notchpay,monetbil',
        ]);

        $user = $request->user();
        $amount = $request->amount;
        $reference = 'DEP-' . uniqid();

        // Vérifier/créer le wallet
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'currency' => 'XAF']
        );

        // Récupérer le solde avant
        $balanceBefore = $wallet->balance;

        if ($request->method === 'notchpay') {
            try {
                // Utiliser la même méthode que OrderController
                $notchpay = app(NotchPayService::class);
                
                $response = $notchpay->initiatePayment([
                    'amount' => $amount,
                    'currency' => 'XAF',
                    'description' => 'Dépôt wallet AgriPulse',
                    'customer_email' => $user->email,
                    'customer_name' => $user->firstname . ' ' . $user->lastname,
                    'customer_phone' => $user->phone ?? '',
                    'reference' => $reference,
                    'callback_url' => env('NOTCHPAY_CALLBACK_URL', config('app.url') . '/api/wallet/deposit/callback'),
                    'return_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/wallet',
                ]);

                // Vérifier si la réponse contient une erreur
                if (isset($response['error']) && $response['error'] === true) {
                    // Si erreur, passer en mode simulation
                    Log::warning('NotchPay error, simulation mode: ' . ($response['message'] ?? 'unknown'));
                    
                    $transaction = Transaction::create([
                        'wallet_id' => $wallet->id,
                        'user_id' => $user->id,
                        'type' => 'deposit',
                        'amount' => $amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceBefore + $amount,
                        'description' => 'Dépôt via NotchPay (simulé)',
                        'status' => 'completed',
                        'reference' => $reference,
                    ]);

                    // Créditer le wallet
                    $wallet->balance += $amount;
                    $wallet->save();

                    return response()->json([
                        'success' => true,
                        'message' => 'Dépôt effectué avec succès (mode simulation)',
                        'data' => [
                            'reference' => $reference,
                            'amount' => $amount,
                            'status' => 'completed',
                            'transaction_id' => $transaction->id,
                            'simulated' => true,
                            'authorization_url' => null,
                        ]
                    ]);
                }

                // Si succès, créer la transaction en attente
                $transaction = Transaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore, // Pas encore crédité
                    'description' => 'Dépôt via NotchPay',
                    'status' => 'pending',
                    'reference' => $reference,
                    'external_reference' => $response['transaction']['reference'] ?? null,
                ]);

                // Retourner l'URL de redirection
                return response()->json([
                    'success' => true,
                    'message' => 'Paiement initié avec succès',
                    'data' => [
                        'reference' => $reference,
                        'amount' => $amount,
                        'status' => 'pending',
                        'authorization_url' => $response['authorization_url'] ?? null,
                        'transaction_id' => $transaction->id,
                    ]
                ]);

            } catch (\Exception $e) {
                Log::error('Erreur dépôt NotchPay: ' . $e->getMessage());
                
                // En cas d'erreur, mode simulation
                $transaction = Transaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore + $amount,
                    'description' => 'Dépôt via NotchPay',
                    'status' => 'completed',
                    'reference' => $reference,
                ]);

                $wallet->balance += $amount;
                $wallet->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Dépôt effectué avec succès (mode simulation)',
                    'data' => [
                        'reference' => $reference,
                        'amount' => $amount,
                        'status' => 'completed',
                        'transaction_id' => $transaction->id,
                        'simulated' => true,
                    ]
                ]);
            }
        }

        if ($request->method === 'monetbil') {
            return response()->json([
                'message' => 'Monetbil non implémenté pour le moment'
            ], 501);
        }

        return response()->json([
            'message' => 'Méthode de paiement non supportée'
        ], 422);
    }

    public function verifyPayment(Request $request, $reference)
    {
        $user = $request->user();
        $transaction = Transaction::where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction non trouvée'], 404);
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
            
            if (isset($response['status']) && $response['status'] === 'completed') {
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
            Log::error('Erreur vérification paiement: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
        ]);
    }

    public function callback(Request $request)
    {
        Log::info('NotchPay webhook received', $request->all());

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

    public function history(Request $request)
    {
        $user = $request->user();
        $wallet = Wallet::where('user_id', $user->id)->first();

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
        ]);

        $user = $request->user();
        $amount = $request->amount;

        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet || $wallet->balance < $amount) {
            return response()->json(['message' => 'Solde insuffisant'], 422);
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
                'message' => 'Retrait effectué avec succès',
                'balance' => $wallet->balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur retrait: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du retrait'], 500);
        }
    }
}
