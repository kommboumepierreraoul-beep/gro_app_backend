<?php

namespace App\Jobs\Moderation;

use App\Models\Comment;
use App\Models\ModerationComment;
use App\Services\Moderation\ModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ModerateCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function handle(ModerationService $moderationService): void
    {
        $result = $moderationService->analyzeComment($this->comment);

        $moderation = $this->comment->moderation ?? new ModerationComment([
            'comment_id' => $this->comment->id,
            'content_hash' => $this->comment->generateContentHash(),
        ]);

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

        $moderation->auditLogs()->create([
            'action' => $result['action'],
            'actor_type' => 'ai',
            'actor_id' => null,
            'payload' => $result,
        ]);
    }
}
