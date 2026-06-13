<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('order_number')->unique();
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', [
                'pending',      // créée, non payée
                'paid',         // payée (webhook NotchPay)
                'preparing',    // vendeur a préparé
                'shipping',     // vendeur a expédié
                'delivered',    // livré (en attente double confirmation)
                'completed',    // argent libéré
                'cancelled'
            ])->default('pending');
            $table->boolean('seller_confirmed_delivery')->default(false);
            $table->boolean('client_confirmed_delivery')->default(false);
            $table->string('shipping_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};