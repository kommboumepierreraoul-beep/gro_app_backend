<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('content');

            $table->string('media_url')->nullable();

            $table->enum('status', [
                'sent',
                'delivered',
                'read'
            ])->default('sent');

            $table->softDeletes();
            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
