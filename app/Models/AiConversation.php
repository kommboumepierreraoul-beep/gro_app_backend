<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiConversation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'model',
        'context_type',
        'context_id',
        'total_tokens',
        'message_count',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'total_tokens' => 'integer',
        'message_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')
            ->orderBy('position');
    }

    public function contextWindowMessages(int $limit = 20)
    {
        return $this->messages()
            ->where('in_context_window', true)
            ->latest('position')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function toApiMessages(int $windowSize = 20): array
    {
        return $this->contextWindowMessages($windowSize)
            ->map(fn(AiMessage $msg) => [
                'role'    => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();
    }

    public function getContextData(): array
    {
        $data = [];

        switch ($this->context_type) {
            case 'post':
                if ($this->context_id && class_exists(Post::class)) {
                    $post = Post::find($this->context_id);
                    if ($post) {
                        $data['post'] = [
                            'id' => $post->id,
                            'title' => $post->title,
                            'content' => $post->content,
                            'user_id' => $post->user_id,
                            'created_at' => $post->created_at,
                        ];
                    }
                }
                break;
            case 'mission':
                if ($this->context_id && class_exists(Mission::class)) {
                    $mission = Mission::find($this->context_id);
                    if ($mission) {
                        $data['mission'] = [
                            'id' => $mission->id,
                            'title' => $mission->title,
                            'description' => $mission->description,
                            'user_id' => $mission->user_id,
                            'created_at' => $mission->created_at,
                        ];
                    }
                }
                break;
            case 'comment':
                if ($this->context_id && class_exists(Comment::class)) {
                    $comment = Comment::find($this->context_id);
                    if ($comment) {
                        $data['comment'] = [
                            'id' => $comment->id,
                            'content' => $comment->content,
                            'user_id' => $comment->user_id,
                            'post_id' => $comment->post_id,
                            'created_at' => $comment->created_at,
                        ];
                    }
                }
                break;
        }

        return $data;
    }
}
