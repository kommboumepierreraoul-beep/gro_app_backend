<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE users
            DROP CONSTRAINT users_role_check;
        ");

        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN ('user', 'admin', 'fournisseur', 'seller'));
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE users
            DROP CONSTRAINT users_role_check;
        ");

        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN ('user', 'admin', 'fournisseur'));
        ");
    }
};
