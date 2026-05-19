<?php

namespace App\Repositories;

use App\Models\Quest;
use App\Models\User;
use App\Repositories\Contracts\QuestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class QuestRepository implements QuestRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        return Quest::query()
            ->whereBelongsTo($user)
            ->with('owner')
            ->withCount('subQuests')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['due_before'] ?? null, fn (Builder $query, mixed $date) => $query->where('due_at', '<=', $date))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->tap(fn (Builder $query) => $this->applySorting($query, $filters['sort'] ?? null))
            ->paginate(15);
    }

    public function findForUser(User $user, int $id): ?Quest
    {
        $quest = Quest::query()
            ->whereBelongsTo($user)
            ->with(['owner', 'parent'])
            ->withCount('subQuests')
            ->find($id);

        if ($quest === null) {
            return null;
        }

        $this->loadSubQuestsRecursively($quest);

        return $quest;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(User $user, array $data): Quest
    {
        $quest = $user->quests()->create($data);

        return $this->loadDefaultRelations($quest);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Quest $quest, array $data): Quest
    {
        $quest->update($data);

        return $this->loadDefaultRelations($quest);
    }

    public function delete(Quest $quest): bool
    {
        return (bool) $quest->delete();
    }

    public function complete(Quest $quest): Quest
    {
        $quest->update(['status' => 'completed']);

        return $this->loadDefaultRelations($quest);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createSubQuest(Quest $parent, array $data): Quest
    {
        $quest = $parent->subQuests()->create([
            ...$data,
            'user_id' => $parent->user_id,
        ]);

        return $this->loadDefaultRelations($quest);
    }

    /**
     * @return Collection<int, Quest>
     */
    public function getSubQuests(Quest $parent): Collection
    {
        return $parent->subQuests()
            ->with('owner')
            ->withCount('subQuests')
            ->get();
    }

    private function applySorting(Builder $query, mixed $sort): void
    {
        match ($sort) {
            'xp_reward_desc' => $query->orderByDesc('xp_reward'),
            'xp_reward_asc' => $query->orderBy('xp_reward'),
            'due_at_asc' => $query->orderBy('due_at'),
            default => $query->latest(),
        };
    }

    private function loadDefaultRelations(Quest $quest): Quest
    {
        return $quest->load('owner')->loadCount('subQuests');
    }

    private function loadSubQuestsRecursively(Quest $quest): void
    {
        $quest->load([
            'subQuests' => fn ($query) => $query
                ->with('owner')
                ->withCount('subQuests'),
        ]);

        $quest->subQuests->each(fn (Quest $subQuest) => $this->loadSubQuestsRecursively($subQuest));
    }
}
