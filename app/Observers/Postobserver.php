<?php

namespace App\Observers;

use App\Jobs\ProcessModerationJob;
use App\Models\Post;

/**
 * Observer sur le modèle Post.
 *
 * Déclenche la modération asynchrone après chaque création de post.
 * Utilise afterCommit() pour s'assurer que la transaction DB est bien
 * terminée avant de dispatcher le job.
 */
class PostObserver
{
    /**
     * Appelé après la création d'un Post.
     * Le contenu est envoyé à la queue de modération IA.
     */
    public function created(Post $post): void
    {
        // On concatène titre + contenu pour une analyse plus complète
        $contentToModerate = implode("\n\n", array_filter([
            $post->title   ?? null,
            $post->content ?? null,
        ]));

        if (blank($contentToModerate)) {
            return;
        }

        // afterCommit() garantit que le post est bien en BDD avant le job
        ProcessModerationJob::dispatch($post, $contentToModerate)->afterCommit();
    }

    /**
     * Appelé après une mise à jour d'un Post.
     * Relance la modération uniquement si le contenu a changé.
     */
    public function updated(Post $post): void
    {
        if (! $post->wasChanged(['title', 'content'])) {
            return;
        }

        $contentToModerate = implode("\n\n", array_filter([
            $post->title   ?? null,
            $post->content ?? null,
        ]));

        if (blank($contentToModerate)) {
            return;
        }

        ProcessModerationJob::dispatch($post, $contentToModerate)->afterCommit();
    }
}
