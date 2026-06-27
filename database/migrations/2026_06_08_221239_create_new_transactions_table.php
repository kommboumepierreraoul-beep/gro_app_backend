<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Supprimer l'ancienne table si elle existe (⚠️ perte des données)
        Schema::dropIfExists('transactions');
        
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->string('reference')->unique();
            $table->enum('type', ['credit', 'debit']);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description')->nullable();
            $table->string('payment_method')->nullable(); // orange_money, mtn_money, card, etc.
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Index pour les recherches fréquentes
            $table->index(['user_id', 'status']);
            $table->index('reference');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};