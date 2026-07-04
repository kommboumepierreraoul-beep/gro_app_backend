<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Supprimer la valeur par défaut
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN type DROP DEFAULT');

            // Convertir en VARCHAR (plus simple et flexible)
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN type TYPE VARCHAR(50) USING type::text');

            // Supprimer le type ENUM s'il existe
            DB::statement('DROP TYPE IF EXISTS activity_logs_type');
        } else {
            // Pour MySQL
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->string('type', 50)->default('system_event')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN type DROP DEFAULT');

            // Créer l'ancien type ENUM
            DB::statement("
                CREATE TYPE activity_logs_type AS ENUM (
                    'product_added',
                    'product_approved',
                    'product_rejected',
                    'user_joined',
                    'system_event'
                )
            ");

            // Reconvertir en ENUM
            DB::statement("
                ALTER TABLE activity_logs 
                ALTER COLUMN type TYPE activity_logs_type 
                USING type::activity_logs_type
            ");

            // Remettre la valeur par défaut
            DB::statement("
                ALTER TABLE activity_logs 
                ALTER COLUMN type SET DEFAULT 'system_event'::activity_logs_type
            ");
        } else {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->enum('type', [
                    'product_added',
                    'product_approved',
                    'product_rejected',
                    'user_joined',
                    'system_event'
                ])->default('system_event')->change();
            });
        }
    }
};
