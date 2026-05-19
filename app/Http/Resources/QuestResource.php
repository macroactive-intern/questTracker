<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'xp_reward' => $this->xp_reward,
            'due_at' => $this->due_at?->toJSON(),
            'sub_quest_count' => $this->whenCounted('subQuests'),
            'owner' => $this->whenLoaded('owner', fn () => (new UserResource($this->owner))->resolve($request)),
            'sub_quests' => $this->whenLoaded(
                'subQuests',
                fn () => QuestResource::collection($this->subQuests)->resolve($request),
            ),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
