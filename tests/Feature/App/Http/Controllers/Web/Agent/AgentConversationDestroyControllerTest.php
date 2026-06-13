<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\User;
use Database\Factories\UserFactory;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

\test('store manager can delete own conversation and messages', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/conversations/destroy', [
            'conversation_id' => $conversation->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent');

    static::assertFalse(Conversation::query()->where('id', $conversation->getKey())->exists());
    static::assertFalse(ConversationMessage::query()->where('conversation_id', $conversation->getKey())->exists());
});

\test('store manager stays on current page when deleting another conversation', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $activeConversation = \createAgentConversation($manager, 'Active chat');
    $otherConversation = \createAgentConversation($manager, 'Other chat');

    $response = $this
        ->from('/agent?conversation=' . $activeConversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/conversations/destroy', [
            'conversation_id' => $otherConversation->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $activeConversation->getKey());

    static::assertTrue(Conversation::query()->where('id', $activeConversation->getKey())->exists());
    static::assertFalse(Conversation::query()->where('id', $otherConversation->getKey())->exists());
    static::assertFalse(ConversationMessage::query()->where('conversation_id', $otherConversation->getKey())->exists());
});

\test('store manager cannot delete foreign conversation', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($other);

    $response = $this->be($manager, 'users')->post('/agent/conversations/destroy', [
        'conversation_id' => $conversation->getKey(),
    ], $this->inertiaHeaders());

    $response->assertRedirect('/agent');

    static::assertTrue(Conversation::query()->where('id', $conversation->getKey())->exists());
    static::assertTrue(ConversationMessage::query()->where('conversation_id', $conversation->getKey())->exists());
});

\test('employee cannot delete conversation', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);
    $conversation = \createAgentConversation($employee);

    $response = $this->be($employee, 'users')->post('/agent/conversations/destroy', [
        'conversation_id' => $conversation->getKey(),
    ], $this->inertiaHeaders());

    $response->assertForbidden();

    static::assertTrue(Conversation::query()->where('id', $conversation->getKey())->exists());
});
