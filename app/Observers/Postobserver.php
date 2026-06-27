<?php

namespace App\Observers;

use App\Jobs\ProcessModerationJob;
use App\Models\ModerationLog;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class PostObserver
{
    public function created(Post $post): void
    {
        $contentToModerate = implode("\n\n", array_filter([
            $post->title ?? null,
            $post->content ?? null,
        ]));

        if (blank($contentToModerate)) {
            return;
        }

        try {
            $log = ModerationLog::create([
                'moderatable_type' => Post::class,
                'moderatable_id' => $post->id,
                'content_hash' => hash('sha256', $contentToModerate),
                'flagged' => false,
                'confidence_score' => 0,
                'reasons' => [],
                'raw_response' => null,
                'status' => 'pending',
                'model_used' => config('ai.model'),
                'processing_time_ms' => 0,
            ]);

            ProcessModerationJob::dispatch($log, $contentToModerate)->afterCommit();

            Log::info('Moderation job dispatched for post', [
                'post_id' => $post->id,
                'log_id' => $log->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch moderation job', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Post $post): void
    {
        if (!$post->wasChanged(['title', 'content'])) {
            return;
        }

        $contentToModerate = implode("\n\n", array_filter([
            $post->title ?? null,
            $post->content ?? null,
        ]));

        if (blank($contentToModerate)) {
            return;
        }

        try {
            $log = ModerationLog::create([
                'moderatable_type' => Post::class,
                'moderatable_id' => $post->id,
                'content_hash' => hash('sha256', $contentToModerate),
                'flagged' => false,
                'confidence_score' => 0,
                'reasons' => [],
                'raw_response' => null,
                'status' => 'pending',
                'model_used' => config('ai.model'),
                'processing_time_ms' => 0,
            ]);

            ProcessModerationJob::dispatch($log, $contentToModerate)->afterCommit();

            Log::info('Re-moderation job dispatched for updated post', [
                'post_id' => $post->id,
                'log_id' => $log->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch re-moderation job', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
