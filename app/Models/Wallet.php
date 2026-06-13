<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'total_credited',
        'total_debited',
        'currency',
    ];

    protected $casts = [
        'balance'        => 'decimal:2',
        'total_credited' => 'decimal:2',
        'total_debited'  => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Créditer le wallet.
     * Si $existingTransaction est fourni, on met à jour celle-ci (dépôt NotchPay).
     * Sinon on crée une nouvelle transaction (virement vendeur, etc.).
     */
    public function credit(float $amount, ?string $description = null, array $metadata = [], ?WalletTransaction $existingTransaction = null): WalletTransaction
    {
        $balanceBefore = $this->balance;

        $this->balance += $amount;
        $this->total_credited += $amount;
        $this->save();

        if ($existingTransaction) {
            // Mettre à jour la transaction existante (dépôt NotchPay)
            $existingTransaction->update([
                'balance_before' => $balanceBefore,
                'balance_after'  => $this->balance,
                'completed_at'   => now(),
            ]);
            return $existingTransaction;
        }

        // Créer une nouvelle transaction (virement vendeur, bonus, etc.)
        return $this->transactions()->create([
            'user_id'        => $this->user_id,
            'type'           => 'credit',
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $this->balance,
            'description'    => $description,
            'metadata'       => $metadata,
            'status'         => 'completed',
            'reference'      => $this->generateReference(),
            'completed_at'   => now(),
        ]);
    }

    public function debit(float $amount, ?string $description = null, array $metadata = []): WalletTransaction
    {
        if ($this->balance < $amount) {
            throw new \Exception('Solde insuffisant');
        }

        $balanceBefore = $this->balance;

        $this->balance -= $amount;
        $this->total_debited += $amount;
        $this->save();

        return $this->transactions()->create([
            'user_id'        => $this->user_id,
            'type'           => 'debit',
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $this->balance,
            'description'    => $description,
            'metadata'       => $metadata,
            'status'         => 'completed',
            'reference'      => $this->generateReference(),
            'completed_at'   => now(),
        ]);
    }

    private function generateReference(): string
    {
        return 'TRX-' . strtoupper(uniqid());
    }
}
