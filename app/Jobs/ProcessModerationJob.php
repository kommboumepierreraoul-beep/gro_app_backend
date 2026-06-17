<?php

namespace App\Jobs;

use App\Models\ModerationLog;
use App\Services\AI\DeepSeekService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessModerationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(
        private ModerationLog $moderationLog,
        private string $content
    ) {}

    public function handle(DeepSeekService $ai): void
    {
        $startTime = microtime(true);

        try {
            Log::info('Processing moderation job', [
                'log_id' => $this->moderationLog->id,
                'content_length' => strlen($this->content),
            ]);

            $result = $ai->moderate($this->content);

            $this->moderationLog->update([
                'flagged' => $result['flagged'],
                'confidence_score' => $result['confidence'],
                'reasons' => $result['reasons'],
                'raw_response' => $result['raw'] ?? null,
                'status' => 'completed',
                'model_used' => $result['model'] ?? config('ai.model'),
                'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000),
            ]);

            if ($result['flagged'] && $result['confidence'] > 0.7) {
                $this->handleFlaggedContent($result);
            }

            Log::info('Moderation completed', [
                'log_id' => $this->moderationLog->id,
                'flagged' => $result['flagged'],
                'confidence' => $result['confidence'],
            ]);
        } catch (\Exception $e) {
            Log::error('Moderation failed', [
                'log_id' => $this->moderationLog->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $this->moderationLog->update([
                'status' => 'failed',
                'raw_response' => ['error' => $e->getMessage()],
                'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                return;
            }

            $this->moderationLog->update(['status' => 'failed_permanent']);
            throw $e;
        }
    }

    private function handleFlaggedContent(array $result): void
    {
        $moderatable = $this->moderationLog->moderatable;

        if ($moderatable && method_exists($moderatable, 'update')) {
            $moderatable->update(['is_hidden' => true]);

            Log::warning('Content hidden by AI moderation', [
                'type' => get_class($moderatable),
                'id' => $moderatable->id,
                'reasons' => $result['reasons'],
                'confidence' => $result['confidence'],
            ]);
        }
    }
}
