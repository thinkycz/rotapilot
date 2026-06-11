<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('authenticated user can view password settings', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/settings/password', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Password');
});

\test('authenticated user can update password', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password',
        'new_password_confirmation' => 'new-password',
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Password');

    $user->refresh();

    static::assertTrue(Resolver::resolveHasher()->check('new-password', $user->getAuthPassword()));
});

\test('password update revokes existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);

    $this->assertDatabaseCount('database_tokens', 1);

    $this->be($user, 'users')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password',
        'new_password_confirmation' => 'new-password',
    ], $this->inertiaHeaders());

    $this->assertDatabaseCount('database_tokens', 0);
});

\test('wrong current password is rejected', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->from('/settings/password')->post('/settings/password', [
        'password' => 'wrong-password',
        'new_password' => 'new-password',
        'new_password_confirmation' => 'new-password',
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('props.errors.password.0', \__('auth.password'));
});

\test('mismatched new password confirmation is rejected', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->from('/settings/password')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password',
        'new_password_confirmation' => 'different-password',
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('props.errors.new_password_confirmation.0', \__('auth.password_mismatch'));
});
