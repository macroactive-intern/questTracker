<?php

namespace App\Providers;

use App\Repositories\Contracts\QuestRepositoryInterface;
use App\Repositories\QuestRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(QuestRepositoryInterface::class, QuestRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
