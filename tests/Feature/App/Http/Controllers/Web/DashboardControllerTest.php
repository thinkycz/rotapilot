<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from dashboard to login', function (): void {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

\test('authenticated user can view dashboard', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'dashboard/Employee');
    $response->assertJsonPath('props.auth.user.email', $user->getEmail());
});

\test('store manager sees manager dashboard', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);

    $response = $this->be($user, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'dashboard/Manager');
});
