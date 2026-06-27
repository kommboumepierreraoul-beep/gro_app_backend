<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete()
                ->unique();

            $table->enum('status', [
                'pending',
                'approved',
                'review',
                'rejected'
            ])->default('pending');

            $table->float('toxicity_score')->nullable();
            $table->float('spam_score')->nullable();
            $table->float('hate_score')->nullable();
            $table->float('violence_score')->nullable();

            $table->json('result_raw')->nullable();
            $table->text('reason')->nullable();

            $table->string('content_hash', 64)->nullable()->index();

            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['post_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'moderated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_posts');
    }
};
