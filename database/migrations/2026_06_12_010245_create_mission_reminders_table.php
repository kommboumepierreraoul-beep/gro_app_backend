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
        Schema::create('mission_reminders', function (Blueprint $table) {
            $table->id();

            // Clés étrangères reliées à tes tables missions et users
            // onDelete('cascade') évite les orphelins si une mission ou un user est supprimé
            $table->foreignId('mission_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->dateTime('remind_at');
            $table->string('type'); // Stockera les const TYPE_* ('approaching_48h', etc.)
            $table->boolean('sent')->default(false);
            $table->dateTime('sent_at')->nullable();

            $table->timestamps();

            // Indexation pour optimiser les requêtes fréquentes du Cron / Scheduler
            $table->index(['sent', 'remind_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_reminders');
    }
};
