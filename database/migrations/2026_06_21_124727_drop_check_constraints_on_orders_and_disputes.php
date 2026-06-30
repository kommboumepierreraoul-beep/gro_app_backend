<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        DB::statement('ALTER TABLE disputes DROP CONSTRAINT IF EXISTS disputes_status_check');
    }

    public function down()
    {
        // Pas de rollback — les contraintes originales étaient trop restrictives
    }
};
