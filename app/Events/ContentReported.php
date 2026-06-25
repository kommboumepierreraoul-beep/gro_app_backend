<?php

namespace App\Events;

use App\Models\ModerationReport;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentReported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ModerationReport $report;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(ModerationReport $report)
    {
        $this->report = $report;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderation'),
            new PrivateChannel('moderation.reports'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'content.reported';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'report_id' => $this->report->id,
            'content_type' => $this->report->content_type,
            'content_id' => $this->report->content_id,
            'reason' => $this->report->reason,
            'reporter_id' => $this->report->reporter_id,
            'status' => $this->report->status,
            'timestamp' => $this->timestamp,
        ];
    }
}
