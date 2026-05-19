<?php

namespace App\Providers;

use App\Events\QuestCompleted;
use App\Listeners\DeliverQuestCompletedWebhook;
use App\Listeners\SubmitQuestXpToLeaderboard;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        QuestCompleted::class => [
            SubmitQuestXpToLeaderboard::class,
            DeliverQuestCompletedWebhook::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
