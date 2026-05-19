<?php

namespace App\Events;

use App\Models\Quest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Quest $quest,
    ) {
    }
}
