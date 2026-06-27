<?php

namespace App\Http\Resources\Mission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'icon'           => $this->icon,
            'color'          => $this->color,
            'sort_order'     => $this->sort_order,
            'missions_count' => $this->whenCounted('missions'),
        ];
    }
}
