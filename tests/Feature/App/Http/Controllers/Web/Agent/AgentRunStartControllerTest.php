<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\AgentRun;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

\test('employee cannot start agent run', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);

    $response = $this->be($employee, 'users')->postJson('/agent/runs/start', [
        'prompt' => 'What stores do I manage?',
    ]);

    $response->assertForbidden();
});

\test('store manager can start background run and persist user message immediately', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);

    $response = $this->be($manager, 'users')->postJson('/agent/runs/start', [
        'prompt' => 'What stores do I manage?',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['run_id', 'conversation_id', 'status']);

    $conversationId = Typer::assertString($response->json('conversation_id'));
    $runId = Typer::assertString($response->json('run_id'));

    static::assertTrue(Conversation::query()
        ->where('id', $conversationId)
        ->where('user_id', $manager->getKey())
        ->exists());

    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->where('role', 'user')
        ->where('content', 'What stores do I manage?')
        ->exists());

    static::assertTrue(AgentRun::query()
        ->where('id', $runId)
        ->where('conversation_id', $conversationId)
        ->where('user_id', $manager->getKey())
        ->exists());

    $run = AgentRun::query()->where('id', $runId)->first();
    static::assertInstanceOf(AgentRun::class, $run);
    static::assertSame(AgentRun::STATUS_COMPLETED, $run->getStatus());
    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->where('role', 'assistant')
        ->where('content', 'Local AI assistant response for: What stores do I manage?')
        ->exists());
});

\test('starting second run in same conversation returns active run id', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $activeRun = Typer::assertInstance(AgentRun::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'status' => AgentRun::STATUS_RUNNING,
        'prompt' => 'Still running',
        'user_message_id' => 'message-id',
        'assistant_content' => '',
    ]), AgentRun::class);

    $response = $this->be($manager, 'users')->postJson('/agent/runs/start', [
        'prompt' => 'Another message',
        'conversation_id' => $conversation->getKey(),
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('run_id', $activeRun->getId());
    $response->assertJsonPath('conversation_id', $conversation->getKey());
});
