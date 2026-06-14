<?php

declare(strict_types=1);

namespace Tests\Feature\App\Ai;

use App\Ai\AgentProposalLinker;
use App\Ai\Agents\SchedulingAgent;
use App\Ai\Tools\AskClarifyingQuestionsTool;
use App\Enums\UserRoleEnum;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('clarifying questions tool returns correct structure', function (): void {
    $tool = new AskClarifyingQuestionsTool();
    $response = $tool->handle(new Request([
        'question' => 'Which store?',
        'options' => ['Prague', 'Brno'],
        'recommended_option' => 'Prague',
    ]));

    $decoded = \json_decode($response, true);

    static::assertSame('Which store?', $decoded['question']);
    static::assertSame(['Prague', 'Brno'], $decoded['options']);
    static::assertSame('Prague', $decoded['recommended_option']);
});

\test('clarifying questions tool rejects invalid payloads', function (array $payload, string $message): void {
    $tool = new AskClarifyingQuestionsTool();
    $decoded = \json_decode($tool->handle(new Request($payload)), true);

    static::assertIsArray($decoded);
    static::assertStringContainsString($message, Typer::assertString($decoded['error'] ?? null));
})->with([
    'empty question' => [['question' => '', 'options' => ['Prague', 'Brno'], 'recommended_option' => 'Prague'], 'non-empty string'],
    'too few options' => [['question' => 'Which store?', 'options' => ['Prague'], 'recommended_option' => 'Prague'], 'between 2 and 5'],
    'prefixed option' => [['question' => 'Which store?', 'options' => ['A: Prague', 'Brno'], 'recommended_option' => 'Brno'], 'must not include letter prefixes'],
    'bad recommendation' => [['question' => 'Which store?', 'options' => ['Prague', 'Brno'], 'recommended_option' => 'Ostrava'], 'recommended_option must be one of'],
]);

\test('linker saves clarifying questions to message meta', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $messageId = (string) Str::uuid();
    $createdAt = \now()->addMinute();

    $message = ConversationMessage::query()->create([
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
                'name' => 'AskClarifyingQuestionsTool',
                'arguments' => [
                    'question' => 'Which employee?',
                    'options' => ['John', 'Pavel'],
                    'recommended_option' => 'John',
                ],
                'result' => \json_encode([
                    'question' => 'Which employee?',
                    'options' => ['John', 'Pavel'],
                    'recommended_option' => 'John',
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
        ->linkQuestionsToLatestAssistantMessage($conversation->getKey());

    $message->refresh();
    $meta = $message->getAttribute('meta');

    static::assertIsArray($meta);
    static::assertArrayHasKey('clarification', $meta);
    static::assertSame('Which employee?', $meta['clarification']['question']);
    static::assertSame(['John', 'Pavel'], $meta['clarification']['options']);
    static::assertSame('John', $meta['clarification']['recommended_option']);
});

\test('linker parses clarifying questions from content JSON fallback', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $messageId = (string) Str::uuid();
    $createdAt = \now()->addMinute();

    $jsonContent = \json_encode([
        'question' => 'Which store?',
        'options' => ['Prague', 'Brno'],
        'recommended_option' => 'Prague',
    ]);

    $message = ConversationMessage::query()->create([
        'id' => $messageId,
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'assistant',
        'content' => "```json\n" . $jsonContent . "\n```",
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    \app(AgentProposalLinker::class)
        ->linkQuestionsToLatestAssistantMessage($conversation->getKey());

    $message->refresh();
    $meta = $message->getAttribute('meta');

    static::assertIsArray($meta);
    static::assertArrayHasKey('clarification', $meta);
    static::assertSame('Which store?', $meta['clarification']['question']);
    static::assertSame(['Prague', 'Brno'], $meta['clarification']['options']);
    static::assertSame('Prague', $meta['clarification']['recommended_option']);

    // The content itself should be rewritten to just the question text for clean UI rendering
    static::assertSame('Which store?', $message->getAttribute('content'));
});

\test('linker ignores invalid clarification shaped json', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $messageId = (string) Str::uuid();
    $createdAt = \now()->addMinute();

    $message = ConversationMessage::query()->create([
        'id' => $messageId,
        'conversation_id' => $conversation->getKey(),
        'user_id' => $manager->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'assistant',
        'content' => "```json\n" . \json_encode([
            'question' => 'This looks like a question?',
            'options' => ['A: Prague', 'Brno'],
            'recommended_option' => 'Prague',
        ]) . "\n```",
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    \app(AgentProposalLinker::class)
        ->linkQuestionsToLatestAssistantMessage($conversation->getKey());

    $message->refresh();
    $meta = $message->getAttribute('meta');

    static::assertIsArray($meta);
    static::assertArrayNotHasKey('clarification', $meta);
});
