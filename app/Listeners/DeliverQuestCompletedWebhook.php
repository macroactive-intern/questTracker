<?php

namespace App\Listeners;

use App\Events\QuestCompleted;
use App\Services\WebhookUrlValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverQuestCompletedWebhook implements ShouldQueue
{
    public int $tries = 5;

    public function __construct(
        private readonly WebhookUrlValidator $urls,
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(QuestCompleted $event): void
    {
        $url = config('services.quest_completed_webhook.url');

        if (! is_string($url) || $url === '') {
            return;
        }

        if (! $this->urls->isSafeHttpUrl($url)) {
            Log::warning('Quest completed webhook URL rejected.', [
                'quest_id' => $event->quest->id,
                'url' => $url,
            ]);

            return;
        }

        Http::timeout(5)
            ->acceptJson()
            ->asJson()
            ->retry(3, 200)
            ->post($url, [
                'event' => 'quest.completed',
                'quest' => [
                    'id' => $event->quest->id,
                    'user_id' => $event->quest->user_id,
                    'title' => $event->quest->title,
                    'status' => $event->quest->status,
                    'xp_reward' => $event->quest->xp_reward,
                    'completed_at' => now()->toJSON(),
                ],
            ])
            ->throw();
    }
}
