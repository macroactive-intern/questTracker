<?php

namespace App\Services;

use App\Events\QuestCompleted;
use App\Models\Quest;
use App\Models\User;
use App\Repositories\Contracts\QuestRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QuestService
{
    public function __construct(
        private readonly QuestRepositoryInterface $quests,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function getPaginatedQuests(User $user, array $filters): LengthAwarePaginator
    {
        return $this->quests->paginateForUser($user, $filters);
    }

    public function getQuest(User $user, int $id): ?Quest
    {
        return $this->quests->findForUser($user, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createQuest(User $user, array $data): Quest
    {
        return $this->quests->create($user, $data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AuthorizationException
     */
    public function updateQuest(User $user, Quest $quest, array $data): Quest
    {
        $this->authorizeOwner($user, $quest);

        return $this->quests->update($quest, $data);
    }

    /**
     * @throws AuthorizationException
     */
    public function deleteQuest(User $user, Quest $quest): bool
    {
        $this->authorizeOwner($user, $quest);

        return $this->quests->delete($quest);
    }

    /**
     * @throws AuthorizationException
     */
    public function completeQuest(User $user, Quest $quest): Quest
    {
        $this->authorizeOwner($user, $quest);

        if ($quest->status === 'completed') {
            return $quest;
        }

        $completedQuest = $this->quests->complete($quest);

        QuestCompleted::dispatch($completedQuest);

        return $completedQuest;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AuthorizationException
     */
    public function createSubQuest(User $user, Quest $parent, array $data): Quest
    {
        $this->authorizeOwner($user, $parent);

        return $this->quests->createSubQuest($parent, $data);
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeOwner(User $user, Quest $quest): void
    {
        if ($quest->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this quest.');
        }
    }
}
