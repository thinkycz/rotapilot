<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('employee cannot stream agent run events', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);

    $response = $this->be($employee, 'users')->get('/agent/runs/stream?run_id=missing');

    $response->assertForbidden();
});

\test('store manager can replay run events after event id', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $run = \createAgentRun($manager, $conversation->getKey(), AgentRun::STATUS_COMPLETED);

    $first = Typer::assertInstance(AgentRunEvent::query()->create([
        'run_id' => $run->getId(),
        'type' => AgentRunEvent::TYPE_TEXT_DELTA,
        'payload' => ['delta' => 'Hello'],
    ]), AgentRunEvent::class);

    AgentRunEvent::query()->create([
        'run_id' => $run->getId(),
        'type' => AgentRunEvent::TYPE_TEXT_DELTA,
        'payload' => ['delta' => ' world'],
    ]);

    AgentRunEvent::query()->create([
        'run_id' => $run->getId(),
        'type' => AgentRunEvent::TYPE_RUN_COMPLETED,
        'payload' => ['status' => AgentRun::STATUS_COMPLETED],
    ]);

    $response = $this->be($manager, 'users')->get('/agent/runs/stream?run_id=' . $run->getId() . '&after_event_id=' . $first->getKey());

    $response->assertOk();
    $content = $response->streamedContent();

    static::assertStringNotContainsString('"delta":"Hello"', $content);
    static::assertStringContainsString('"delta":" world"', $content);
    static::assertStringContainsString('"type":"run_completed"', $content);
    static::assertStringContainsString('[DONE]', $content);
});

\test('store manager cannot stream foreign run', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($other);
    $run = \createAgentRun($other, $conversation->getKey(), AgentRun::STATUS_RUNNING);

    $response = $this->be($manager, 'users')->get('/agent/runs/stream?run_id=' . $run->getId());

    $response->assertNotFound();
});

\test('stream endpoint validates required run id', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);

    $response = $this->be($manager, 'users')->getJson('/agent/runs/stream');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('run_id');
});
