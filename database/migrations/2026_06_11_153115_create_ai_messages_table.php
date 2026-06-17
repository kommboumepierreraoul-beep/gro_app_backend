<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->foreign('conversation_id')
                ->references('id')
                ->on('ai_conversations')
                ->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('position')->default(0); // ordre dans la conversation
            $table->boolean('in_context_window')->default(true); // dans la fenêtre glissante
            $table->json('meta')->nullable(); // finish_reason, model_version, etc.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'position']);
            $table->index(['conversation_id', 'in_context_window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
