<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Activer l'extension PostGIS si pas déjà active
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        Schema::create('missions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ulid', 26)->unique(); // identifiant public URL-safe

            // Relations
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('mission_categories')->nullOnDelete();

            // Contenu
            $table->string('title', 255);
            $table->text('description');
            $table->text('desired_profile')->nullable();

            // Durée
            $table->string('duration_type', 20)->default('flexible');
            // ENUM: hours | day | days | weeks | flexible
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->date('start_date')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Localisation (PostGIS géré via raw SQL - Blueprint ne supporte pas geography)
            $table->string('location_label', 255)->nullable();
            $table->unsignedSmallInteger('diffusion_radius_km')->default(25);
            $table->string('diffusion_scope', 20)->default('radius'); // radius | platform

            // Rémunération
            $table->string('remuneration_type', 20);
            // ENUM: fixed | daily_rate | hourly_rate | negotiable | in_kind | volunteer
            $table->decimal('remuneration_amount', 12, 2)->nullable();
            $table->string('remuneration_currency', 3)->default('XAF');
            $table->text('remuneration_conditions')->nullable();

            // Candidature
            $table->jsonb('contact_methods')->default('[]');
            // ex: [{"type":"whatsapp","value":"+237600000000"},{"type":"app_message"}]
            $table->jsonb('application_form')->default('[]');
            // ex: [{"id":"q1","label":"...","type":"boolean","required":true}]
            $table->boolean('allow_attachments')->default(false);
            $table->unsignedSmallInteger('max_applications')->nullable();

            // État
            $table->string('status', 20)->default('draft');
            // ENUM: draft|published|filled|in_progress|completed|archived|suspended|cancelled
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Stats dénormalisées (mis à jour via triggers)
            $table->unsignedInteger('applications_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        // Ajouter la colonne PostGIS geography après la création
        DB::statement('ALTER TABLE missions ADD COLUMN location_point geography(POINT, 4326)');

        // Index
        DB::statement('CREATE INDEX idx_missions_location ON missions USING GIST (location_point)');
        DB::statement('CREATE INDEX idx_missions_status ON missions (status)');
        DB::statement('CREATE INDEX idx_missions_author ON missions (author_id)');
        DB::statement('CREATE INDEX idx_missions_expires ON missions (expires_at)');
        DB::statement('CREATE INDEX idx_missions_status_expires ON missions (status, expires_at) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_increment_applications ON mission_applications');
        DB::statement('DROP TRIGGER IF EXISTS trg_decrement_applications ON mission_applications');
        DB::statement('DROP FUNCTION IF EXISTS increment_mission_applications_count()');
        DB::statement('DROP FUNCTION IF EXISTS decrement_mission_applications_count()');
        Schema::dropIfExists('missions');
    }
};
