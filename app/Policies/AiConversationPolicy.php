<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiConversationPolicy
{
    use HandlesAuthorization;

    public function view(User $user, AiConversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }

    public function delete(User $user, AiConversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }

    public function update(User $user, AiConversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
