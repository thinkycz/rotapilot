<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordNewPasswordSettedNotification;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view forgot password page', function (): void {
    $response = $this->get('/forgot-password', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
});

\test('unknown email returns validation error', function (): void {
    $response = $this->post('/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertStatus(422);
});

\test('known email updates password and sends notification', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
    $response->assertSessionHas('success');

    $user->refresh();

    static::assertNotSame($originalHash, $user->getAuthPassword());

    Notification::assertSentTo($user, PasswordNewPasswordSettedNotification::class);
});

\test('known email revokes existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    DatabaseToken::inject()
        ->login($user->getTable(), $user);

    $this->assertDatabaseCount('database_tokens', 1);

    $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ]);

    $this->assertDatabaseCount('database_tokens', 0);
});

\test('broker flow creates a token and sends a reset link notification', function (): void {
    Notification::fake();

    Config::inject()->assign('auth.passwords.users.send_raw_password', false);

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
    $response->assertSessionHas('success');

    $user->refresh();

    // Password must not have been overwritten; the broker flow only issues a token.
    static::assertSame($originalHash, $user->getAuthPassword());

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

\test('broker flow end to end: forgot then reset updates password', function (): void {
    Config::inject()->assign('auth.passwords.users.send_raw_password', false);

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ]);

    // The broker flow issues a token that ResetPasswordController can consume.
    $broker = Resolver::resolvePasswordBroker('users');
    $token = $broker->createToken($user);
    static::assertTrue($broker->tokenExists($user, $token));

    $resetResponse = $this->post('/reset-password', [
        'email' => $user->getEmail(),
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        'token' => $token,
    ]);

    $resetResponse->assertRedirect('/dashboard');

    $user->refresh();

    static::assertNotSame($originalHash, $user->getAuthPassword());
});
