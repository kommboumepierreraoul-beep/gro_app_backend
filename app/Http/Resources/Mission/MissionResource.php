<?php

namespace App\Http\Resources\Mission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'ulid'              => $this->ulid,
            'title'             => $this->title,
            'description_short' => Str::limit($this->description, 120),
            'status'            => $this->status,

            // Catégorie
            'category'          => $this->whenLoaded('category', fn() => [
                'id'    => $this->category->id,
                'name'  => $this->category->name,
                'slug'  => $this->category->slug,
                'icon'  => $this->category->icon,
                'color' => $this->category->color,
            ]),

            // Auteur
            'author'            => $this->whenLoaded('author', fn() => [
                'id'        => $this->author->id,
                'name'      => $this->author->name,
                'firstname' => $this->author->firstname,
                'lastname'  => $this->author->lastname,
                'avatar'    => $this->author->profile?->avatar,
            ]),

            // Durée
            'duration_type'     => $this->duration_type,
            'duration_value'    => $this->duration_value,
            'duration_label'    => $this->duration_label,
            'start_date'        => $this->start_date?->toDateString(),
            'expires_at'        => $this->expires_at?->toIso8601String(),

            // Localisation
            'location_label'    => $this->location_label,
            'distance_km'       => $this->distance_km ?? null, // injecté par scopeWithDistance

            // Rémunération
            'remuneration_type'     => $this->remuneration_type,
            'remuneration_amount'   => $this->remuneration_amount,
            'remuneration_currency' => $this->remuneration_currency,
            'remuneration_label'    => $this->remuneration_label,

            // Stats
            'applications_count' => $this->applications_count,
            'pending_count'      => $this->pending_count ?? null, // via withCount auteur
            'views_count'        => $this->views_count,
            'max_applications'   => $this->max_applications,
            'is_open'            => $this->isOpen(),
            'is_full'            => $this->isFull(),

            // Méta
            'allow_attachments'  => $this->allow_attachments,
            'contact_methods'    => $this->contact_methods,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
