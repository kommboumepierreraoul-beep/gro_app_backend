<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_logs DROP CONSTRAINT IF EXISTS activity_logs_type_check');
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN type DROP DEFAULT');
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN type TYPE VARCHAR(50) USING type::text');
            DB::statement("ALTER TABLE activity_logs ALTER COLUMN type SET DEFAULT 'system_event'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_logs DROP CONSTRAINT IF EXISTS activity_logs_type_check');
        }
    }
};
