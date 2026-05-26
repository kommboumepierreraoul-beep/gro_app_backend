<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('actor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('type', [
                'like_post',
                'like_comment',
                'comment',
                'reply',
                'follow',
                'share',
                'mention',
                'announcement'
            ]);

            $table->nullableMorphs('notifiable');

            $table->string('message');

            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_notifications');
    }
};
