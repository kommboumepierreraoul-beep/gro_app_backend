<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('disputes', function (Blueprint $table) {
            if (!Schema::hasColumn('disputes', 'mode')) {
                $table->string('mode')->default('amiable')->after('resolution');
            }
            if (!Schema::hasColumn('disputes', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('mode');
            }
            if (!Schema::hasColumn('disputes', 'admin_question')) {
                $table->text('admin_question')->nullable()->after('admin_notes');
            }
        });

        // PostgreSQL : changer le type de la colonne status en text avec contrainte
        DB::statement("ALTER TABLE disputes ALTER COLUMN status TYPE VARCHAR(50)");
        DB::statement("ALTER TABLE disputes ALTER COLUMN status SET DEFAULT 'pending'");
        
        // Mettre à jour les anciens statuts si nécessaire
        DB::statement("UPDATE disputes SET status = 'negotiation' WHERE status = 'investigating'");
        DB::statement("UPDATE disputes SET status = 'resolved_by_admin' WHERE status = 'resolved'");
    }

    public function down()
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['mode', 'escalated_at', 'admin_question']);
        });
    }
};
