<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\User;
use Thinkycz\LaravelCore\Support\Resolver;

\test('guest can view register page', function (): void {
    $response = $this->get('/register', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/Register');
});

\test('user can register with database token cookie', function (): void {
    $response = $this->post('/register', [
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'en',
    ]);

    $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

    $response->assertRedirect('/dashboard');
    $response->assertCookie(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());
    $this->assertDatabaseHas('users', [
        'email' => 'new-user@example.com',
        'locale' => 'en',
    ]);
    \expect($user->getRole())->toBe(UserRoleEnum::StoreManager);
    \expect($user->getIsActive())->toBeTrue();
});

\test('public registration only creates store managers', function (): void {
    $this->post('/register', [
        'email' => 'manager-attempt@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'en',
    ]);

    $user = User::query()->where('email', 'manager-attempt@example.com')->firstOrFail();
    \expect($user->getRole())->toBe(UserRoleEnum::StoreManager);
    \expect($user->isEmployee())->toBeFalse();
});

\test('register rejects mismatched password confirmation', function (): void {
    $response = $this->from('/register')->post('/register', [
        'email' => 'mismatch@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
        'locale' => 'en',
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('component', 'auth/Register');
    $response->assertJsonPath('props.errors.password.0', 'The password field confirmation does not match.');
    $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
});

\test('registered user password is hashed only once', function (): void {
    $this->post('/register', [
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'en',
    ]);

    $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

    static::assertTrue(Resolver::resolveHasher()->check('password', $user->getAuthPassword()));
});
