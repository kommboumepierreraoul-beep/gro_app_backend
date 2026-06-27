<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte et recréer avec toutes les valeurs
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check 
            CHECK (type IN ('credit', 'debit', 'deposit', 'withdraw', 'payment', 'refund'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check 
            CHECK (type IN ('credit', 'debit'))");
    }
};