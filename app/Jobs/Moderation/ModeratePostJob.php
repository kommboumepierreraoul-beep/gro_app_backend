<?php

namespace App\Jobs\Moderation;

use App\Models\Post;
use App\Models\ModerationPost;
use App\Services\Moderation\ModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ModeratePostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Post $post) {}

    public function handle(ModerationService $moderationService): void
    {
        // Analyser le post
        $result = $moderationService->analyzePost($this->post);

        // Créer ou récupérer la modération
        $moderation = $this->post->moderation ?? new ModerationPost([
            'post_id' => $this->post->id,
            'content_hash' => $this->post->generateContentHash(),
        ]);

        // Mettre à jour
        $moderation->fill([
            'status' => match ($result['action']) {
                'approve' => 'approved',
                'review' => 'review',
                'reject' => 'rejected',
                default => 'pending',
            },
            'toxicity_score' => $result['toxicity'] ?? 0,
            'spam_score' => $result['spam'] ?? 0,
            'hate_score' => $result['hate'] ?? 0,
            'violence_score' => $result['violence'] ?? 0,
            'result_raw' => $result,
            'reason' => $result['reason'] ?? null,
            'moderated_at' => now(),
        ]);

        $moderation->save();

        // Audit log
        $moderation->auditLogs()->create([
            'action' => $result['action'],
            'actor_type' => 'ai',
            'actor_id' => null,
            'payload' => $result,
        ]);
    }
}
