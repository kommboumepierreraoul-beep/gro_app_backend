<?php

namespace App\Jobs;

use App\Models\ModerationLog;
use App\Models\User;
use App\Notifications\ContentFlaggedNotification;
use App\Services\AI\DeepSeekService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessModerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(
        private readonly \Illuminate\Database\Eloquent\Model $moderatable,
        private readonly string $contentToModerate
    ) {
        $this->onQueue('ai-moderation');
    }

    public function handle(DeepSeekService $deepSeek): void
    {
        Log::info('ProcessModerationJob: start', [
            'type' => get_class($this->moderatable),
            'id'   => $this->moderatable->id,
        ]);

        $result = $deepSeek->moderateContent($this->contentToModerate);
        $action = $this->determineAction($result['score']);

        $log = ModerationLog::create([
            'moderatable_type' => get_class($this->moderatable),
            'moderatable_id'   => $this->moderatable->id,
            'is_safe'          => $result['is_safe'],
            'score'            => $result['score'],
            'categories'       => $result['categories'],
            'reason'           => $result['reason'],
            'action'           => $action,
        ]);

        $this->applyAction($action, $result);

        // Correction : ordre des paramètres
        if (in_array($action, ['flagged', 'rejected'])) {
            $this->notifyModerators($this->moderatable, $log, $action);
        }

        Log::info('ProcessModerationJob: done', [
            'type'   => get_class($this->moderatable),
            'id'     => $this->moderatable->id,
            'action' => $action,
            'score'  => $result['score'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessModerationJob: failed permanently', [
            'type'    => get_class($this->moderatable),
            'id'      => $this->moderatable->id,
            'message' => $exception->getMessage(),
        ]);

        if (method_exists($this->moderatable, 'update')) {
            $this->moderatable->update(['moderation_status' => 'approved']);
        }
    }

    private function determineAction(float $score): string
    {
        if ($score >= 0.8) {
            return 'rejected';
        }
        if ($score >= 0.5) {
            return 'flagged';
        }
        return 'approved';
    }

    private function applyAction(string $action, array $result): void
    {
        if (! method_exists($this->moderatable, 'update')) {
            return;
        }

        match ($action) {
            'approved' => $this->moderatable->update([
                'moderation_status' => 'approved',
                'moderated_at' => now(),
            ]),
            'flagged' => $this->moderatable->update([
                'moderation_status' => 'pending_review',
                'moderation_flags'  => json_encode($result['categories'] ?? []),
                'moderated_at' => now(),
            ]),
            'rejected' => $this->moderatable->update([
                'moderation_status' => 'rejected',
                'moderation_flags'  => json_encode($result['categories'] ?? []),
                'published_at'      => null,
                'moderated_at' => now(),
            ]),
        };
    }

    // Correction : ordre des paramètres
    private function notifyModerators($content, ModerationLog $log, string $action): void
    {
        $moderators = User::whereIn('role', ['moderator', 'admin'])->get();

        if ($moderators->isEmpty()) {
            Log::info('No moderators found to notify', [
                'content_type' => get_class($content),
                'content_id' => $content->id,
                'action' => $action
            ]);
            return;
        }

        Notification::send($moderators, new ContentFlaggedNotification(
            $content,  // 1er paramètre : le contenu
            $log,      // 2ème paramètre : le log
            $action    // 3ème paramètre : l'action
        ));
    }
}
