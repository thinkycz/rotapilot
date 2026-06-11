<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Password\PasswordResetController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('user can reset password', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'reset@example.com',
        'password' => 'old_password',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $token = Resolver::resolvePasswordBroker('users')->createToken($user);

    $data = [
        'token' => $token,
        'email' => $user->getEmail(),
        'password' => 'new_password',
        'password_confirmation' => 'new_password',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordResetController::class), $data);

    $response->assertOk();

    $response->assertCookie(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());

    $this->assertAuthenticatedAs($user);

    $user->refresh();

    \expect(Resolver::resolveHasher()->check('new_password', $user->getAuthPassword()))->toBeTrue();
});

\test('password reset fails with invalid token', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'reset@example.com',
        'password' => 'old_password',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $data = [
        'token' => 'invalid_token',
        'email' => $user->getEmail(),
        'password' => 'new_password',
        'password_confirmation' => 'new_password',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordResetController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors('token');
});

\test('password reset fails with invalid email', function (): void {
    $data = [
        'token' => 'invalid_token',
        'email' => 'nonexistent@example.com',
        'password' => 'new_password',
        'password_confirmation' => 'new_password',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordResetController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors('email');
});

\test('password reset requires valid data', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordResetController::class));

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['token', 'email', 'password', 'password_confirmation']);
});

\test('password reset fails with mismatched confirmation', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'reset@example.com',
        'password' => 'old_password',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $token = Resolver::resolvePasswordBroker('users')->createToken($user);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordResetController::class), [
        'token' => $token,
        'email' => $user->getEmail(),
        'password' => 'new_password',
        'password_confirmation' => 'different_password',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('password_confirmation');
});

\test('reset revokes existing database tokens', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'reset@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);
    $this->assertDatabaseCount('database_tokens', 1);

    $token = Resolver::resolvePasswordBroker('users')->createToken($user);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordResetController::class), [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertOk();

    $this->assertDatabaseCount('database_tokens', 1);
});
