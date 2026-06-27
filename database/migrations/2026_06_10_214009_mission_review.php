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
        Schema::create('mission_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewee_id')->constrained('users')->onDelete('cascade');
            $table->enum('direction', ['author_to_applicant', 'applicant_to_author']);
            $table->tinyInteger('rating')->unsigned()->comment('Rating from 1 to 5');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Empêcher les doublons: un utilisateur ne peut pas reviewer deux fois la même relation
            $table->unique(['mission_id', 'reviewer_id', 'reviewee_id', 'direction'], 'unique_review');

            // Index pour les performances
            $table->index(['mission_id', 'direction']);
            $table->index('reviewer_id');
            $table->index('reviewee_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_reviews');
    }
};
