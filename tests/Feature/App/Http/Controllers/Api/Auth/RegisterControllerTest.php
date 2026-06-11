<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Thinkycz\LaravelCore\Support\Resolver;

\test('user can register and receive own resource', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(RegisterController::class), [
        'email' => 'new@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'locale' => 'en',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(201);
    $response->assertJsonPath('data.attributes.email', 'new@example.com');
    $response->assertJsonPath('data.type', 'users');

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    \expect($user->getRole())->toBe(UserRoleEnum::StoreManager);
    \expect($user->getIsActive())->toBeTrue();
});

\test('registered user password is hashed only once', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(RegisterController::class), [
        'email' => 'new@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'locale' => 'en',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(201);

    $user = User::query()->where('email', 'new@example.com')->first();
    \expect($user)->not->toBeNull();
    \expect($user->getAuthPassword())->not->toBe('password1');
    \expect(Hash::check('password1', (string) $user->getAuthPassword()))->toBeTrue();
});

\test('register creates database token for user', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(RegisterController::class), [
        'email' => 'new@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'locale' => 'en',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(201);

    $this->assertDatabaseCount('database_tokens', 1);
});

\test('register rejects duplicate email', function (): void {
    $existing = UserFactory::new()->createOne([
        'email' => 'taken@example.com',
    ]);
    \expect($existing)->toBeInstanceOf(User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(RegisterController::class), [
        'email' => 'taken@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'locale' => 'en',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(422);
});

\test('register rejects short password', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(RegisterController::class), [
        'email' => 'new@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
        'locale' => 'en',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(422);
});

\test('register rejects invalid email', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(RegisterController::class), [
        'email' => 'not-an-email',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'locale' => 'en',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(422);
});

\test('register rejects mismatched password confirmation', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(RegisterController::class), [
        'email' => 'mismatch@example.com',
        'password' => 'password1',
        'password_confirmation' => 'different',
        'locale' => 'en',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
});
