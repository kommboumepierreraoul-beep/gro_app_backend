<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('balance_before', 10, 2)->nullable()->change();
            $table->decimal('balance_after', 10, 2)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('balance_before', 10, 2)->nullable(false)->change();
            $table->decimal('balance_after', 10, 2)->nullable(false)->change();
        });
    }
};