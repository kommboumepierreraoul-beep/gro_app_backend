<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();

            $table->string('reason', 40);
            // ENUM: spam | inappropriate | scam | duplicate | misleading | other
            $table->text('details')->nullable();

            $table->string('status', 20)->default('pending');
            // ENUM: pending | reviewed | dismissed | action_taken

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->unique(['mission_id', 'reporter_id']);
        });

        DB::statement('CREATE INDEX idx_reports_status ON mission_reports (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_reports');
    }
};
