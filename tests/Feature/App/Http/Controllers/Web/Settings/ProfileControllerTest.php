<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('authenticated user can view profile settings', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/settings/profile', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Profile');
});

\test('authenticated user can update profile settings', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/settings/profile', [
        'email' => 'updated@example.com',
        'locale' => 'cs',
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Profile');
    $this->assertDatabaseHas('users', [
        'id' => $user->getKey(),
        'email' => 'updated@example.com',
        'locale' => 'cs',
    ]);
});
