<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('model')->default('deepseek-chat');
            $table->string('context_type')->default('general'); // general, post, thread, etc.
            $table->unsignedBigInteger('context_id')->nullable();  // ID de l'objet lié
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('message_count')->default(0);
            $table->json('meta')->nullable(); // données supplémentaires libres
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
