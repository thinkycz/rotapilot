<?php

declare(strict_types=1);

use App\Ai\Agents\SchedulingAgent;
use App\Enums\UserRoleEnum;
use App\Models\AgentActionProposal;
use App\Models\AgentRun;
use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Typer;

\pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Architecture', 'Feature', 'Unit');

/**
 * Create an isolated store_manager user and a default store owned by
 * them. The pair mirrors what the registration flow produces in
 * production: a manager with their first managed store ready for
 * feature tests that need a populated workspace.
 *
 * @return array{0: User, 1: Store}
 */
function createIsolatedUserWithStore(): array
{
    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
        'is_active' => true,
    ]), User::class);

    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    return [$user, $store];
}

/**
 * Create a store managed by the given user.
 */
function managedStoreFor(User $manager, string $name): Store
{
    $store = Typer::assertInstance(StoreFactory::new()->createOne(['name' => $name]), Store::class);

    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return $store;
}

/**
 * Create an employee assigned to the given store.
 */
function employeeFor(Store $store, string $name): EmployeeProfile
{
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne([
        'name' => $name,
        'email' => 'private@example.test',
        'phone' => '+420 123',
        'max_hours_per_week' => 30,
    ]), EmployeeProfile::class);

    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return $employee;
}

/**
 * Decode JSON returned by an AI tool.
 *
 * @return array<int|string, mixed>
 */
function decodeToolJson(string $json): array
{
    $decoded = \json_decode($json, true);

    \assert(\is_array($decoded));

    return $decoded;
}

/**
 * Assert that the response carries an Inertia flash message
 * (success or error) under the given key.
 *
 * Works for both redirect responses (via the Inertia re-flash
 * mechanism) and 200 OK Inertia render responses (via the
 * `flash` prop the HandleInertiaRequests middleware injects).
 */
function assertInertiaFlash(TestResponse $response, string $key, mixed $message): void
{
    try {
        $response->assertInertiaFlash($key, $message);

        return;
    } catch (Throwable) {
        // Fall through to the props check for 200 OK render responses.
    }

    $flashed = $response->json('props.flash.' . $key);

    \expect($flashed)->toBe($message);
}

/**
 * Create a persisted Laravel AI conversation for agent controller tests.
 */
function createAgentConversation(User $user, string $title = 'Staffing chat'): Conversation
{
    $conversation = Typer::assertInstance(Conversation::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'title' => $title,
    ]), Conversation::class);

    ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $user->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'user',
        'content' => 'Show me shifts',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $user->getKey(),
        'agent' => SchedulingAgent::class,
        'role' => 'assistant',
        'content' => 'No shifts are scheduled.',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    return $conversation;
}

/**
 * Create a persisted agent proposal for controller and service tests.
 *
 * @param array<int, array<string, mixed>> $actions
 */
function createAgentProposal(
    User $user,
    string $conversationId,
    array $actions,
    string $status = AgentActionProposal::STATUS_PENDING,
): AgentActionProposal {
    return Typer::assertInstance(AgentActionProposal::query()->create([
        'conversation_id' => $conversationId,
        'user_id' => $user->getKey(),
        'status' => $status,
        'summary' => 'Apply these changes',
        'actions' => $actions,
        'result' => null,
    ]), AgentActionProposal::class);
}

/**
 * Create a persisted agent run for controller tests.
 */
function createAgentRun(User $user, string $conversationId, string $status = AgentRun::STATUS_RUNNING): AgentRun
{
    return Typer::assertInstance(AgentRun::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => $user->getKey(),
        'status' => $status,
        'prompt' => 'What stores do I manage?',
        'user_message_id' => (string) Str::uuid(),
        'assistant_message_id' => null,
        'assistant_content' => '',
        'error' => null,
        'started_at' => $status === AgentRun::STATUS_QUEUED ? null : \now(),
        'finished_at' => \in_array($status, AgentRun::activeStatuses(), true) ? null : \now(),
    ]), AgentRun::class);
}
