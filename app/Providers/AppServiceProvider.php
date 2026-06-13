<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AgentConversationContext;
use App\Ai\Agents\SchedulingAgent;
use Illuminate\Support\ServiceProvider;
use Thinkycz\LaravelCore\Support\Env;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AgentConversationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(\base_path('vendor/laravel/ai/database/migrations'));

        $env = Env::inject();

        if ($env->appEnvIs(['local', 'testing']) && $env->parseNullableString('OPENROUTER_API_KEY') === null) {
            SchedulingAgent::fake(static fn(string $prompt): string => 'Local AI assistant response for: ' . $prompt);
        }
    }
}
