<?php

namespace App\Providers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ Enregistrer la règle de validation exists_with
        Validator::extend('exists_with', function ($attribute, $value, $parameters, $validator) {
            $contextType = $validator->getData()['context_type'] ?? null;

            if (empty($contextType)) {
                return true; // Pas de contexte = pas de validation
            }

            // ✅ Vérifier que le modèle existe avant de l'utiliser
            $modelMap = [
                'post' => \App\Models\Post::class,
                'comment' => \App\Models\Comment::class,
                // ✅ Supprimer 'thread' si le modèle n'existe pas
                // 'thread' => \App\Models\Thread::class,
            ];

            $model = $modelMap[$contextType] ?? null;

            if (!$model || !class_exists($model)) {
                return true; // Type inconnu ou modèle inexistant = pas de validation
            }

            return $model::where('id', $value)->exists();
        });
    }
}
