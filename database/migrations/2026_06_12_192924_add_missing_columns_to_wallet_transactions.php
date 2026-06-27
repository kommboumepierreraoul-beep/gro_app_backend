<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_transactions', 'balance_before'))
                $table->decimal('balance_before', 15, 2)->nullable()->after('amount');
            if (!Schema::hasColumn('wallet_transactions', 'balance_after'))
                $table->decimal('balance_after', 15, 2)->nullable()->after('balance_before');
            if (!Schema::hasColumn('wallet_transactions', 'metadata'))
                $table->json('metadata')->nullable()->after('description');
            if (!Schema::hasColumn('wallet_transactions', 'completed_at'))
                $table->timestamp('completed_at')->nullable()->after('status');
            if (!Schema::hasColumn('wallet_transactions', 'wallet_id'))
                $table->unsignedBigInteger('wallet_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['balance_before', 'balance_after', 'metadata', 'completed_at', 'wallet_id']);
        });
    }
};
