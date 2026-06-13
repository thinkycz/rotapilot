<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\AgentProjectContext;
use App\Ai\Tools\GetAvailabilityTool;
use App\Ai\Tools\GetEmployeesTool;
use App\Ai\Tools\GetShiftsTool;
use App\Ai\Tools\GetStoresTool;
use App\Ai\Tools\ProposeSchedulingChangesTool;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Thinkycz\LaravelCore\Support\Config;

class SchedulingAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

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
            5. Use `ProposeSchedulingChangesTool` when the manager asks you to create or change stores, availability, shifts, assignments, unassignments, safe deletions, or auto-fill. This creates a pending proposal only; the manager must review and apply it.

            CRITICAL RULES:
            - Never make up information. If a query requires data that you do not have, use the tools to fetch it.
            - When live data is needed, do not answer from memory and do not write a fake tool call or JSON payload in the chat. You must actually invoke the relevant tool.
            - When proposing writes, never output an `actions` JSON object or code block as the proposal. You must call `ProposeSchedulingChangesTool` so RotaPilot creates a real pending proposal card.
            - If you need IDs before proposing a change, first call the lookup tools to get real IDs, then call `ProposeSchedulingChangesTool`.
            - Before changing an existing assignment's time, call `GetShiftsTool`, identify the existing `assignment_id`, and propose `shift.unassign` before the replacement `shift.assign`.
            - For `shift.assign`, keep `start_time` and `end_time` inside the shift requirement window. Do not create a second assignment for the same employee, shift requirement, and assignment start time.
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
    }
}
