<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            // Type d'annonce
            $table->enum('listing_type', ['sale', 'rent'])
                ->default('sale')
                ->after('status');

            // Prix unitaire
            $table->decimal('unit_price', 12, 2)
                ->nullable()
                ->after('price');

            // Quantité en stock
            $table->integer('stock_quantity')
                ->nullable()
                ->after('stock');

            // Condition de livraison
            $table->string('delivery_condition')
                ->nullable()
                ->after('stock_quantity');

            // Informations agricoles
            $table->string('variety')
                ->nullable()
                ->after('delivery_condition');

            $table->string('origin')
                ->nullable()
                ->after('variety');

            $table->string('certification')
                ->nullable()
                ->after('origin');

            $table->date('harvest_date')
                ->nullable()
                ->after('certification');

            $table->date('expiration_date')
                ->nullable()
                ->after('harvest_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->dropColumn([
                'listing_type',
                'unit_price',
                'stock_quantity',
                'delivery_condition',
                'variety',
                'origin',
                'certification',
                'harvest_date',
                'expiration_date',
            ]);
        });
    }
};;
