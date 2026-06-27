<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
        CREATE OR REPLACE FUNCTION increment_mission_applications_count()
        RETURNS TRIGGER AS $$
        BEGIN
            UPDATE missions
            SET applications_count = applications_count + 1
            WHERE id = NEW.mission_id;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    ");

        DB::statement("
        CREATE TRIGGER trg_increment_applications
        AFTER INSERT ON mission_applications
        FOR EACH ROW EXECUTE FUNCTION increment_mission_applications_count();
    ");

        DB::statement("
        CREATE OR REPLACE FUNCTION decrement_mission_applications_count()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NEW.status = 'withdrawn' AND OLD.status != 'withdrawn' THEN
                UPDATE missions
                SET applications_count = GREATEST(applications_count - 1, 0)
                WHERE id = NEW.mission_id;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    ");

        DB::statement("
        CREATE TRIGGER trg_decrement_applications
        AFTER UPDATE ON mission_applications
        FOR EACH ROW EXECUTE FUNCTION decrement_mission_applications_count();
    ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_triggers');
    }
};
