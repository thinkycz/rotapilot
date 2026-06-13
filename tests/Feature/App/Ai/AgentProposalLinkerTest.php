<?php

declare(strict_types=1);

use App\Ai\AgentProposalLinker;
use App\Ai\Agents\SchedulingAgent;
use App\Enums\UserRoleEnum;
use App\Models\AgentActionProposal;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

\test('linker populates message_id on proposals referenced by the latest assistant tool_results', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $proposal = \createAgentProposal($manager, $conversation->getKey(), []);

    $messageId = (string) Str::uuid();
    $createdAt = \now()->addSeconds(10);
    ConversationMessage::query()->create([
        'id' => $messageId,
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [
            [
                'id' => 'tool-call-id',
                'name' => 'ProposeSchedulingChangesTool',
                'arguments' => ['summary' => 'Test', 'actions' => []],
                'result' => \json_encode([
                    'proposal_id' => $proposal->getKey(),
                    'status' => 'pending',
                ]),
                'result_id' => null,
            ],
        ],
        'usage' => [],
        'meta' => [],
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    \app(AgentProposalLinker::class)
        ->linkProposalsToLatestAssistantMessage($conversation->getKey());

    static::assertSame($messageId, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getMessageId());
});

\test('linker does not overwrite an already-assigned message_id', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $originalMessageId = (string) Str::uuid();
    $proposal = \createAgentProposal($manager, $conversation->getKey(), []);
    $proposal->forceFill(['message_id' => $originalMessageId])->save();

    $newerMessageId = (string) Str::uuid();
    $createdAt = \now()->addSeconds(10);
    ConversationMessage::query()->create([
        'id' => $newerMessageId,
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [
            [
                'id' => 'tool-call-id',
                'name' => 'ProposeSchedulingChangesTool',
                'arguments' => [],
                'result' => \json_encode([
                    'proposal_id' => $proposal->getKey(),
                    'status' => 'pending',
                ]),
                'result_id' => null,
            ],
        ],
        'usage' => [],
        'meta' => [],
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    \app(AgentProposalLinker::class)
        ->linkProposalsToLatestAssistantMessage($conversation->getKey());

    static::assertSame($originalMessageId, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getMessageId());
});

\test('linker leaves proposals alone when the assistant tool_results are empty or unrelated', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $proposal = \createAgentProposal($manager, $conversation->getKey(), []);

    $createdAt = \now()->addSeconds(10);
    ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'assistant',
        'content' => 'No tools were used.',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    \app(AgentProposalLinker::class)
        ->linkProposalsToLatestAssistantMessage($conversation->getKey());

    static::assertNull(Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getMessageId());
});
