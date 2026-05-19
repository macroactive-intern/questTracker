<?php

use App\Events\QuestCompleted;
use App\Listeners\DeliverQuestCompletedWebhook;
use App\Models\Quest;
use App\Models\User;
use App\Services\WebhookUrlValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('uses a queued listener with retries for quest completed webhooks', function (): void {
    $listener = new DeliverQuestCompletedWebhook(app(WebhookUrlValidator::class));

    expect($listener)->toBeInstanceOf(ShouldQueue::class)
        ->and($listener->tries)->toBe(5)
        ->and($listener->backoff())->toBe([10, 30, 60, 120]);
});

it('validates webhook urls before delivery to reduce ssrf risk', function (): void {
    $validator = app(WebhookUrlValidator::class);

    expect($validator->isSafeHttpUrl('https://8.8.8.8/webhooks/quest-completed'))->toBeTrue()
        ->and($validator->isSafeHttpUrl('ftp://8.8.8.8/webhooks/quest-completed'))->toBeFalse()
        ->and($validator->isSafeHttpUrl('https://user:secret@8.8.8.8/webhooks/quest-completed'))->toBeFalse()
        ->and($validator->isSafeHttpUrl('http://127.0.0.1:8080/webhooks/quest-completed'))->toBeFalse()
        ->and($validator->isSafeHttpUrl('http://10.0.0.5/webhooks/quest-completed'))->toBeFalse()
        ->and($validator->isSafeHttpUrl('http://localhost/webhooks/quest-completed'))->toBeFalse();
});

it('delivers a quest completed webhook payload when configured with a safe url', function (): void {
    config(['services.quest_completed_webhook.url' => 'https://8.8.8.8/webhooks/quest-completed']);
    Http::fake([
        '8.8.8.8/*' => Http::response(['ok' => true]),
    ]);

    $user = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $user->id,
        'title' => 'Light the beacon',
        'status' => 'completed',
        'xp_reward' => 150,
    ]);

    $listener = new DeliverQuestCompletedWebhook(app(WebhookUrlValidator::class));
    $listener->handle(new QuestCompleted($quest));

    Http::assertSent(fn ($request) => $request->url() === 'https://8.8.8.8/webhooks/quest-completed'
        && $request['event'] === 'quest.completed'
        && $request['quest']['id'] === $quest->id
        && $request['quest']['user_id'] === $user->id
        && $request['quest']['xp_reward'] === 150);
});

it('does not deliver webhook requests to unsafe urls', function (): void {
    config(['services.quest_completed_webhook.url' => 'http://127.0.0.1:8080/internal']);
    Http::fake();
    Log::spy();

    $quest = Quest::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Unsafe webhook target',
        'status' => 'completed',
    ]);

    $listener = new DeliverQuestCompletedWebhook(app(WebhookUrlValidator::class));
    $listener->handle(new QuestCompleted($quest));

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
});
