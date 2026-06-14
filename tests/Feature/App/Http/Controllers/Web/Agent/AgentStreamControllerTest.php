<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\User;
use Database\Factories\UserFactory;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

\test('employee cannot stream agent responses', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);

    $response = $this->be($employee, 'users')->postJson('/agent/stream', [
        'prompt' => 'What stores do I manage?',
    ], [
        'Accept' => 'text/event-stream',
    ]);

    $response->assertForbidden();
});

\test('store manager can stream a new fake response in local test mode', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);

    $response = $this->be($manager, 'users')->postJson('/agent/stream', [
        'prompt' => 'What stores do I manage?',
    ], [
        'Accept' => 'text/event-stream',
    ]);

    $response->assertOk();

    $content = $response->streamedContent();

    static::assertStringContainsString('data:', $content);
    static::assertStringContainsString('[DONE]', $content);

    $conversation = Conversation::query()
        ->where('user_id', $manager->getKey())
        ->first();

    static::assertNotNull($conversation);
    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('role', 'user')
        ->where('content', 'What stores do I manage?')
        ->exists());

    static::assertSame(1, ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('role', 'user')
        ->where('content', 'What stores do I manage?')
        ->count());

    static::assertFalse(ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('meta->provisional', true)
        ->exists());
});

\test('store manager can continue own conversation but not foreign conversation', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $ownConversation = \createAgentConversation($manager, 'Own chat');
    $foreignConversation = \createAgentConversation($other, 'Foreign chat');

    $ownResponse = $this->be($manager, 'users')->postJson('/agent/stream', [
        'prompt' => 'Continue this chat',
        'conversation_id' => $ownConversation->getKey(),
    ], [
        'Accept' => 'text/event-stream',
    ]);

    $ownResponse->assertOk();
    $ownResponse->streamedContent();

    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $ownConversation->getKey())
        ->where('content', 'Continue this chat')
        ->exists());

    $foreignResponse = $this->be($manager, 'users')->postJson('/agent/stream', [
        'prompt' => 'Do not attach this to someone else',
        'conversation_id' => $foreignConversation->getKey(),
    ], [
        'Accept' => 'text/event-stream',
    ]);

    $foreignResponse->assertOk();
    $foreignResponse->streamedContent();

    static::assertFalse(ConversationMessage::query()
        ->where('conversation_id', $foreignConversation->getKey())
        ->where('content', 'Do not attach this to someone else')
        ->exists());
});
