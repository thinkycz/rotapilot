<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('employee cannot cancel agent run', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);

    $response = $this->be($employee, 'users')->postJson('/agent/runs/cancel', [
        'run_id' => 'missing',
    ]);

    $response->assertForbidden();
});

\test('store manager can cancel own active run', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $run = \createAgentRun($manager, $conversation->getKey(), AgentRun::STATUS_RUNNING);

    $response = $this->be($manager, 'users')->postJson('/agent/runs/cancel', [
        'run_id' => $run->getId(),
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', AgentRun::STATUS_CANCELLED);

    $run->refresh();
    static::assertSame(AgentRun::STATUS_CANCELLED, $run->getStatus());
    static::assertTrue(AgentRunEvent::query()
        ->where('run_id', $run->getId())
        ->where('type', AgentRunEvent::TYPE_RUN_CANCELLED)
        ->exists());
});

\test('store manager cannot cancel foreign run', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($other);
    $run = \createAgentRun($other, $conversation->getKey(), AgentRun::STATUS_RUNNING);

    $response = $this->be($manager, 'users')->postJson('/agent/runs/cancel', [
        'run_id' => $run->getId(),
    ]);

    $response->assertNotFound();
});

\test('cancelled run allows a later new run', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    \createAgentRun($manager, $conversation->getKey(), AgentRun::STATUS_CANCELLED);

    $response = $this->be($manager, 'users')->postJson('/agent/runs/start', [
        'prompt' => 'Start again',
        'conversation_id' => $conversation->getKey(),
    ]);

    $response->assertOk();
});
