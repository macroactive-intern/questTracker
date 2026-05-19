<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class LeaderboardEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rank' => (int) $this->value('rank'),
            'user_id' => (int) $this->value('user_id'),
            'score' => (int) $this->value('score'),
            'achieved_at' => $this->dateValue('achieved_at'),
        ];
    }

    private function value(string $key): mixed
    {
        return data_get($this->resource, $key);
    }

    private function dateValue(string $key): ?string
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toJSON();
        }

        return Carbon::parse($value)->toJSON();
    }
}
