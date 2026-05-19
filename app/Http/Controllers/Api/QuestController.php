<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestRequest;
use App\Http\Requests\UpdateQuestRequest;
use App\Http\Resources\QuestCollection;
use App\Http\Resources\QuestResource;
use App\Models\Quest;
use App\Models\User;
use App\Services\QuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class QuestController extends Controller
{
    public function __construct(
        private readonly QuestService $quests,
    ) {
    }

    public function index(Request $request): QuestCollection
    {
        return new QuestCollection(
            $this->quests->getPaginatedQuests($request->user(), $request->query()),
        );
    }

    public function store(StoreQuestRequest $request): JsonResponse
    {
        $quest = $this->quests->createQuest($request->user(), $request->validated());

        return (new QuestResource($quest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $quest): QuestResource
    {
        return new QuestResource($this->findQuestOrFail($request->user(), $quest));
    }

    public function update(UpdateQuestRequest $request, int $quest): QuestResource
    {
        $quest = $this->quests->updateQuest(
            $request->user(),
            $this->findQuestOrFail($request->user(), $quest),
            $request->validated(),
        );

        return new QuestResource($quest);
    }

    public function destroy(Request $request, int $quest): JsonResponse
    {
        $this->quests->deleteQuest($request->user(), $this->findQuestOrFail($request->user(), $quest));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function complete(Request $request, int $quest): QuestResource
    {
        $quest = $this->quests->completeQuest($request->user(), $this->findQuestOrFail($request->user(), $quest));

        return new QuestResource($quest);
    }

    public function subQuests(Request $request, int $quest): AnonymousResourceCollection
    {
        $subQuests = $this->quests->getSubQuests(
            $request->user(),
            $this->findQuestOrFail($request->user(), $quest),
        );

        return QuestResource::collection($subQuests);
    }

    public function storeSubQuest(StoreQuestRequest $request, int $quest): JsonResponse
    {
        $data = $request->validated();
        unset($data['parent_id']);

        $subQuest = $this->quests->createSubQuest(
            $request->user(),
            $this->findQuestOrFail($request->user(), $quest),
            $data,
        );

        return (new QuestResource($subQuest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function findQuestOrFail(User $user, int $id): Quest
    {
        return $this->quests->getQuest($user, $id) ?? abort(Response::HTTP_NOT_FOUND);
    }
}
