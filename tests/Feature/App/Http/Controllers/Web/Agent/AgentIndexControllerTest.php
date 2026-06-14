<?php

declare(strict_types=1);

use App\Ai\Agents\SchedulingAgent;
use App\Enums\UserRoleEnum;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AgentActionProposal;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
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

\test('internal proposal confirmation messages are hidden from chat history', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'user',
        'content' => 'This is an internal confirmation event, not the manager\'s latest natural-language message.',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'assistant',
        'content' => 'I have applied the proposal successfully.',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    $response = $this->be($manager, 'users')->get('/agent?conversation=' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonCount(2, 'props.messages');
    $response->assertJsonPath('props.messages.0.content', 'Show me shifts');
    $response->assertJsonPath('props.messages.1.content', 'No shifts are scheduled.');
});

\test('proposals expose message_id field for inline tool-result rendering', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $assistantMessage = $conversation->messages()
        ->where('role', 'assistant')
        ->orderBy('created_at', 'asc')
        ->first();
    static::assertNotNull($assistantMessage);
    $messageId = Typer::assertString($assistantMessage->getAttribute('id'));

    $linked = \createAgentProposal($manager, $conversation->getKey(), [], AgentActionProposal::STATUS_PENDING);
    $linked->forceFill(['message_id' => $messageId])->save();

    $orphan = \createAgentProposal($manager, $conversation->getKey(), [], AgentActionProposal::STATUS_PENDING);

    $response = $this->be($manager, 'users')->get('/agent?conversation=' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $proposals = $response->json('props.proposals');
    static::assertCount(2, $proposals);

    $byId = [];
    foreach ($proposals as $proposal) {
        static::assertArrayHasKey('message_id', $proposal);
        $byId[$proposal['id']] = $proposal;
    }

    static::assertSame($messageId, $byId[$linked->getKey()]['message_id']);
    static::assertNull($byId[$orphan->getKey()]['message_id']);
});

\test('store manager page exposes active background run', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $run = \createAgentRun($manager, $conversation->getKey(), AgentRun::STATUS_RUNNING);
    $run->forceFill(['assistant_content' => 'Partial answer'])->save();

    $event = Typer::assertInstance(AgentRunEvent::query()->create([
        'run_id' => $run->getId(),
        'type' => AgentRunEvent::TYPE_TEXT_DELTA,
        'payload' => ['delta' => 'Partial answer'],
    ]), AgentRunEvent::class);

    $response = $this->be($manager, 'users')->get('/agent?conversation=' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.activeRun.id', $run->getId());
    $response->assertJsonPath('props.activeRun.status', AgentRun::STATUS_RUNNING);
    $response->assertJsonPath('props.activeRun.assistant_content', 'Partial answer');
    $response->assertJsonPath('props.activeRun.last_event_id', $event->getKey());
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
