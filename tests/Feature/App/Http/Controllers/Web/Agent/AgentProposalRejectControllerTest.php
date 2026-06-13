<?php

declare(strict_types=1);

use App\Models\AgentActionProposal;
use App\Models\User;
use Database\Factories\UserFactory;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

\test('manager can reject own pending proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
        'locale' => 'sk',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $proposal = \createAgentProposal($manager, $conversation->getKey(), []);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/reject', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    static::assertSame(AgentActionProposal::STATUS_REJECTED, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('role', 'assistant')
        ->where('content', 'like', 'Dobre, návrh%')
        ->exists());
    static::assertFalse(ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('content', 'like', '%internal confirmation event%')
        ->exists());
});

\test('employee cannot reject proposal', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'employee',
    ]), User::class);

    $this->be($employee, 'users')->post('/agent/proposals/reject', [
        'proposal_id' => 1,
    ], $this->inertiaHeaders())->assertForbidden();
});

\test('manager cannot reject foreign proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $conversation = \createAgentConversation($other);
    $proposal = \createAgentProposal($other, $conversation->getKey(), []);

    $this->be($manager, 'users')->post('/agent/proposals/reject', [
        'proposal_id' => $proposal->getKey(),
    ], $this->inertiaHeaders())->assertNotFound();

    static::assertSame(AgentActionProposal::STATUS_PENDING, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
});

\test('stale proposal reject url redirects back to agent page', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);

    $response = $this->be($manager, 'users')->get('/agent/proposals/reject');

    $response->assertRedirect('/agent');
});
