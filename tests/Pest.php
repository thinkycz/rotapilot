<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\Store;
use App\Models\User;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Typer;

\pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Architecture', 'Feature', 'Unit');

/**
 * Create an isolated store_manager user and a default store owned by
 * them. The pair mirrors what the registration flow produces in
 * production: a manager with their first managed store ready for
 * feature tests that need a populated workspace.
 *
 * @return array{0: User, 1: Store}
 */
function createIsolatedUserWithStore(): array
{
    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
        'is_active' => true,
    ]), User::class);

    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    return [$user, $store];
}

/**
 * Assert that the response carries an Inertia flash message
 * (success or error) under the given key.
 *
 * Works for both redirect responses (via the Inertia re-flash
 * mechanism) and 200 OK Inertia render responses (via the
 * `flash` prop the HandleInertiaRequests middleware injects).
 */
function assertInertiaFlash(TestResponse $response, string $key, mixed $message): void
{
    try {
        $response->assertInertiaFlash($key, $message);

        return;
    } catch (Throwable) {
        // Fall through to the props check for 200 OK render responses.
    }

    $flashed = $response->json('props.flash.' . $key);

    \expect($flashed)->toBe($message);
}
