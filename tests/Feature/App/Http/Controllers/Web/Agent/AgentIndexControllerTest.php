<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from agent page to login', function (): void {
    $response = $this->get('/agent');

    $response->assertRedirect('/login');
});

\test('employee cannot view agent page', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);

    $response = $this->be($employee, 'users')->get('/agent', $this->inertiaHeaders());

    $response->assertForbidden();
});

\test('store manager can view agent page', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);

    $response = $this->be($manager, 'users')->get('/agent', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'agent/Index');
    $response->assertJsonPath('props.conversationId', null);
    $response->assertJsonPath('props.messages', []);
});

\test('store manager can load own conversation messages', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $response = $this->be($manager, 'users')->get('/agent?conversation=' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.conversationId', $conversation->getKey());
    $response->assertJsonPath('props.messages.0.role', 'user');
    $response->assertJsonPath('props.messages.0.content', 'Show me shifts');
    $response->assertJsonPath('props.messages.1.role', 'assistant');
});

\test('store manager cannot load foreign or unknown conversations', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $foreignConversation = \createAgentConversation($other);

    $foreignResponse = $this->be($manager, 'users')->get('/agent?conversation=' . $foreignConversation->getKey(), $this->inertiaHeaders());
    $foreignResponse->assertOk();
    $foreignResponse->assertJsonPath('props.conversationId', null);
    $foreignResponse->assertJsonPath('props.messages', []);

    $unknownResponse = $this->be($manager, 'users')->get('/agent?conversation=missing', $this->inertiaHeaders());
    $unknownResponse->assertOk();
    $unknownResponse->assertJsonPath('props.conversationId', null);
    $unknownResponse->assertJsonPath('props.messages', []);
});

\test('shared conversation list only includes manager owned conversations', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    \createAgentConversation($manager, 'Owned chat');
    \createAgentConversation($other, 'Foreign chat');

    $response = $this->be($manager, 'users')->get('/agent', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.conversations.0.title', 'Owned chat');

    $titles = \array_column(Typer::assertArray($response->json('props.conversations')), 'title');

    static::assertNotContains('Foreign chat', $titles);
});

\test('employee shared conversation list is empty', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);
    \createAgentConversation($employee, 'Employee chat');

    $this->be($employee, 'users');
    $middleware = new HandleInertiaRequests();
    $request = Request::create('/dashboard', 'GET');
    $shared = $middleware->share($request);

    static::assertSame([], ($shared['conversations'])());
});
