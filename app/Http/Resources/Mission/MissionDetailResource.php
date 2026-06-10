<?php

namespace App\Http\Resources\Mission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'ulid'            => $this->ulid,
            'title'           => $this->title,
            'description'     => $this->description,
            'desired_profile' => $this->desired_profile,
            'status'          => $this->status,

            'category' => $this->whenLoaded('category', fn() => [
                'id'    => $this->category->id,
                'name'  => $this->category->name,
                'slug'  => $this->category->slug,
                'icon'  => $this->category->icon,
                'color' => $this->category->color,
            ]),

            // Auteur enrichi avec réputation
            'author' => $this->whenLoaded('author', fn() => [
                'id'            => $this->author->id,
                'name'          => $this->author->name,
                'avatar'        => $this->author->avatar,
                'rating'        => $this->author->mission_rating ?? null,
                'reviews_count' => $this->author->mission_reviews_count ?? null,
            ]),

            // Durée
            'duration_type'  => $this->duration_type,
            'duration_value' => $this->duration_value,
            'duration_label' => $this->duration_label,
            'start_date'     => $this->start_date?->toDateString(),
            'expires_at'     => $this->expires_at?->toIso8601String(),

            // Localisation (coordonnées brutes si disponibles)
            'location_label'      => $this->location_label,
            'lat'                 => $this->lat ?? null,
            'lng'                 => $this->lng ?? null,
            'distance_km'         => $this->distance_km ?? null,
            'diffusion_radius_km' => $this->diffusion_radius_km,
            'diffusion_scope'     => $this->diffusion_scope,

            // Rémunération complète
            'remuneration_type'       => $this->remuneration_type,
            'remuneration_amount'     => $this->remuneration_amount,
            'remuneration_currency'   => $this->remuneration_currency,
            'remuneration_conditions' => $this->remuneration_conditions,
            'remuneration_label'      => $this->remuneration_label,

            // Candidature
            'contact_methods'   => $this->contact_methods,
            'application_form'  => $this->application_form,
            'allow_attachments' => $this->allow_attachments,
            'max_applications'  => $this->max_applications,
            'is_open'           => $this->isOpen(),
            'is_full'           => $this->isFull(),

            // Stats
            'applications_count' => $this->applications_count,
            'accepted_count'     => $this->accepted_count ?? null,
            'views_count'        => $this->views_count,

            // Candidature de l'utilisateur connecté (si injectée)
            'user_application' => $this->when(
                isset($this->user_application),
                fn() => $this->user_application ? [
                    'id'     => $this->user_application->id,
                    'status' => $this->user_application->status,
                ] : null
            ),

            // Avis
            'reviews' => $this->whenLoaded(
                'reviews',
                fn() =>
                $this->reviews->map(fn($r) => [
                    'id'        => $r->id,
                    'rating'    => $r->rating,
                    'comment'   => $r->comment,
                    'direction' => $r->direction,
                    'reviewer'  => [
                        'id'     => $r->reviewer->id,
                        'name'   => $r->reviewer->name,
                        'avatar' => $r->reviewer->avatar,
                    ],
                    'created_at' => $r->created_at->toIso8601String(),
                ])
            ),

            'filled_at'    => $this->filled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
        ];
    }
}
