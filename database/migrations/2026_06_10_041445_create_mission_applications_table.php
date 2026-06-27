<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_applications', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('applicant_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Méthode de candidature
            $table->string('method', 20)->default('form');
            // ENUM: form | app_message | whatsapp | email

            // Contenu de la candidature
            $table->jsonb('form_responses')->default('{}');
            $table->text('motivation')->nullable();
            $table->jsonb('attachment_paths')->default('[]');

            // État
            $table->string('status', 20)->default('pending');
            // ENUM: pending | accepted | rejected | withdrawn | confirmed

            // Notes internes auteur
            $table->text('author_note')->nullable();
            $table->text('rejection_reason')->nullable();

            // Timestamps d'état
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            // Un seul dossier actif par mission par candidat
            $table->unique(['mission_id', 'applicant_id']);
        });

        DB::statement('CREATE INDEX idx_applications_mission ON mission_applications (mission_id)');
        DB::statement('CREATE INDEX idx_applications_applicant ON mission_applications (applicant_id)');
        DB::statement('CREATE INDEX idx_applications_status ON mission_applications (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_applications');
    }
};
