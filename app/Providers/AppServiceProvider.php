<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AgentConversationContext;
use App\Ai\Agents\SchedulingAgent;
use Illuminate\Support\ServiceProvider;
use Thinkycz\LaravelCore\Support\Config;

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

        $config = Config::inject();

        if ($config->appEnvIs(['local', 'testing']) && $config->parseNullableString('ai.providers.openrouter.key') === null) {
            SchedulingAgent::fake(static fn(string $prompt): string => 'Local AI assistant response for: ' . $prompt);
        }
    }
}
