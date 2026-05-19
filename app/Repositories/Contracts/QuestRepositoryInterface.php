<?php

namespace App\Repositories\Contracts;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface QuestRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator;

    public function findForUser(User $user, int $id): ?Quest;

    /**
     * @param array<string, mixed> $data
     */
    public function create(User $user, array $data): Quest;

    /**
     * @param array<string, mixed> $data
     */
    public function update(Quest $quest, array $data): Quest;

    public function delete(Quest $quest): bool;

    public function complete(Quest $quest): Quest;

    /**
     * @param array<string, mixed> $data
     */
    public function createSubQuest(Quest $parent, array $data): Quest;

    /**
     * @return Collection<int, Quest>
     */
    public function getSubQuests(Quest $parent): Collection;
}
