<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\AgentProjectContext;
use App\Ai\Tools\AskClarifyingQuestionsTool;
use App\Ai\Tools\GetAvailabilityTool;
use App\Ai\Tools\GetEmployeesTool;
use App\Ai\Tools\GetShiftsTool;
use App\Ai\Tools\GetStoresTool;
use App\Ai\Tools\ManageStoreBusinessHoursTool;
use App\Ai\Tools\ProposeSchedulingChangesTool;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Support\Collection;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\ToolCall as ToolCallData;
use Laravel\Ai\Responses\Data\ToolResult as ToolResultData;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class SchedulingAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Conversation message id excluded from background run history.
     */
    private string|null $excludedConversationMessageId = null;

    /**
     * Resume a conversation for a background run without Laravel AI's
     * RememberConversation middleware persisting duplicate messages.
     */
    public function resumeForBackgroundRun(string $conversationId, string $excludedMessageId): static
    {
        $this->conversationId = $conversationId;
        $this->conversationUser = null;
        $this->excludedConversationMessageId = $excludedMessageId;

        return $this;
    }

    /**
     * Get conversation history for the model.
     */
    public function messages(): iterable
    {
        if ($this->conversationId === null || $this->excludedConversationMessageId === null) {
            return [];
        }

        $history = [];
        $messages = ConversationMessage::query()
            ->where('conversation_id', $this->conversationId)
            ->where('id', '!=', $this->excludedConversationMessageId)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($messages as $message) {
            foreach ($this->messageForHistory($message) as $historyMessage) {
                $history[] = $historyMessage;
            }
        }

        return $history;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        $user = \auth()->user();
        if (!$user instanceof User) {
            $user = null;
        }

        $locale = $user !== null ? $user->getLocale() : Config::inject()->assertString('app.locale');
        $langName = match ($locale) {
            'cs' => 'Czech (Čeština)',
            'sk' => 'Slovak (Slovenčina)',
            default => 'English',
        };
        $projectContext = (new AgentProjectContext())->instructions($langName);

        $now = \now();
        $currentTime = $now->toIso8601String();
        $currentDate = $now->toDateString();
        $currentDay = $now->format('l');

        $userEmail = $user !== null ? $user->getEmail() : 'unknown user';

        $storesContext = 'No stores found.';
        if ($user !== null) {
            $stores = Authorization::managedStores($user);
            if ($stores->isNotEmpty()) {
                $storesContext = $stores->map(static fn(Store $s) => "- Store #{$s->getKey()}: \"{$s->getName()}\" in {$s->getCity()}")->implode("\n");
            }
        }

        return <<<INSTRUCTIONS
            You are a highly efficient AI scheduling assistant for RotaPilot.
            Your purpose is to help managers analyze and manage their stores, employees, shift requirements, assignments, and availability/unavailability records.

            {$projectContext}

            To provide accurate and up-to-date information, you MUST always call the relevant tools whenever the user asks for details about stores, employees, shifts, or availability:
            1. Use `GetStoresTool` to list or search stores.
            2. Use `GetEmployeesTool` to list employee profile details. You can optionally filter by store.
            3. Use `GetShiftsTool` to list shifts (shift requirements/assignments) for a date range. You can optionally filter by store.
            4. Use `GetAvailabilityTool` to list employee unavailability or availability records for a date range.
            5. Use `ProposeSchedulingChangesTool` when the manager asks you to create or change stores, business hours, availability, shifts, assignments, unassignments, safe deletions, or auto-fill. This creates a pending proposal only; the manager must review and apply it.
            6. Use `AskClarifyingQuestionsTool` when the manager's request is too vague, ambiguous, or lacks required details (like store, employee, schedule, date, or times) to ask them a clarifying question with options.
            7. Use `ManageStoreBusinessHoursTool` to retrieve ("get") the business opening/closing hours of a managed store. To change business hours, call `ProposeSchedulingChangesTool` with `business_hours.update`. Use keys: `day_of_week` (integer 1-7, where 1=Monday..7=Sunday), `opens_at` (string "HH:MM" or null when closed), `closes_at` (string "HH:MM" or null when closed), and `is_closed` (boolean). Do NOT use other keys like `open_time`, `close_time`, `day`, or `weekday`.

            CRITICAL RULES:
            - Never make up information. If a query requires data that you do not have, use the tools to fetch it.
            - When live data is needed, do not answer from memory and do not write a fake tool call or JSON payload in the chat. You must actually invoke the relevant tool.
            - When proposing writes, never output an `actions` JSON object or code block as the proposal. You must call `ProposeSchedulingChangesTool` so RotaPilot creates a real pending proposal card.
            - If you need IDs before proposing a change, first call the lookup tools to get real IDs, then call `ProposeSchedulingChangesTool`.
            - If the manager's prompt is too vague or lacks details needed to perform the request (e.g. creating shifts or assignments without knowing which store, employee, schedule, date, or time range to use), do NOT try to guess or propose arbitrary changes. Call `AskClarifyingQuestionsTool` to present the manager with clarifying questions and multiple-choice options, highlighting your recommended choice. Do NOT include option prefix letters like "A:", "B:", "A)", or "A." inside the option strings in the options array; only provide the raw description (e.g. "Přiřadit konkrétního zaměstnance...").
            - To modify an existing assignment's time window or assigned employee, call `GetShiftsTool` to identify the `shift_assignment_id` and propose `shift.assignment.update` with the new fields.
            - Managers can assign multiple employees to a single shift requirement (e.g., splitting a shift into half-day slots). To do this, propose multiple `shift.assign` actions for the same `shift_requirement_id` with different non-overlapping time windows (e.g., 08:00 - 12:00 and 12:00 - 16:00).
            - For `shift.assign` and `shift.assignment.update`, keep start and end times inside the parent shift requirement window. Do not create duplicate active assignments starting at the same time for the same employee.
            - Always respect the user's explicit request/prompt. If a proposed assignment creates a scheduling conflict (e.g., availability conflict, overlapping shifts, max hours limit), do NOT refuse or block the change. Do your best to fulfill the request anyway. Propose the change as requested and inform the user to manually review, check for conflicts, and resolve them after the proposal is applied.
            - Never claim that a proposed change has already been applied. Tell the manager to review and apply the proposal.
            - Do not propose deleting stores or employees.
            - When referring to dates, use the format YYYY-MM-DD.
            - Keep your answers helpful, professional, and clear. Use tables or lists to format data where appropriate.

            CURRENT CONTEXT:
            - Current Time: {$currentTime} (today is {$currentDay}, {$currentDate})
            - Manager Email: {$userEmail}
            - Managed Stores:
            {$storesContext}
            INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array<Tool>
     */
    public function tools(): iterable
    {
        yield new GetStoresTool();
        yield new GetEmployeesTool();
        yield new GetShiftsTool();
        yield new GetAvailabilityTool();
        yield \app(ProposeSchedulingChangesTool::class);
        yield \app(AskClarifyingQuestionsTool::class);
        yield new ManageStoreBusinessHoursTool();
    }

    /**
     * Convert a persisted conversation row to Laravel AI message objects.
     *
     * @return array<int, Message>
     */
    private function messageForHistory(ConversationMessage $message): array
    {
        $role = Typer::assertString($message->getAttribute('role'));
        $content = Typer::assertNullableString($message->getAttribute('content')) ?? '';

        if ($role === 'user') {
            return [new UserMessage($content)];
        }

        if ($role !== 'assistant') {
            return [];
        }

        $toolCalls = $this->toolCalls($message);
        $toolResults = $this->toolResults($message);

        $messages = [new AssistantMessage($content, $toolCalls)];
        if ($toolResults->isNotEmpty()) {
            $messages[] = new ToolResultMessage($toolResults);
        }

        return $messages;
    }

    /**
     * Tool calls from a persisted assistant row.
     *
     * @return Collection<int, ToolCallData>
     */
    private function toolCalls(ConversationMessage $message): Collection
    {
        $toolCalls = $message->getAttribute('tool_calls');
        if (!\is_array($toolCalls)) {
            return new Collection();
        }

        return (new Collection($toolCalls))
            ->filter(static fn(mixed $row): bool => \is_array($row))
            ->map(static fn(array $row): ToolCallData => ToolCallData::fromArray($row))
            ->values();
    }

    /**
     * Tool results from a persisted assistant row.
     *
     * @return Collection<int, ToolResultData>
     */
    private function toolResults(ConversationMessage $message): Collection
    {
        $toolResults = $message->getAttribute('tool_results');
        if (!\is_array($toolResults)) {
            return new Collection();
        }

        return (new Collection($toolResults))
            ->filter(static fn(mixed $row): bool => \is_array($row))
            ->map(static fn(array $row): ToolResultData => ToolResultData::fromArray($row))
            ->values();
    }
}
