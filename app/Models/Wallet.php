<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\WalletFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
        'balance' => 'decimal:2',
        'total_credited' => 'decimal:2',
        'total_debited' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Créditer le wallet
 public function credit(float $amount, ?string $description = null, array $metadata = []): Transaction
{
    $balanceBefore = $this->balance;

    $this->balance += $amount;
    $this->total_credited += $amount;
    $this->save();

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

public function debit(float $amount, ?string $description = null, array $metadata = []): Transaction
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