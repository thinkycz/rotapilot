<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\GetAvailabilityTool;
use App\Ai\Tools\GetEmployeesTool;
use App\Ai\Tools\GetShiftsTool;
use App\Ai\Tools\GetStoresTool;
use App\Ai\Tools\ProposeSchedulingChangesTool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class SchedulingAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
            You are a highly efficient AI scheduling assistant for RotaPilot.
            Your purpose is to help managers analyze and manage their stores, employees, shift requirements, assignments, and availability/unavailability records.

            To provide accurate and up-to-date information, you MUST always call the relevant tools whenever the user asks for details about stores, employees, shifts, or availability:
            1. Use `GetStoresTool` to list the stores managed by the current user.
            2. Use `GetEmployeesTool` to list employee profile details. You can optionally filter by store.
            3. Use `GetShiftsTool` to list shifts (shift requirements/assignments) for a date range. You can optionally filter by store.
            4. Use `GetAvailabilityTool` to list employee unavailability or availability records for a date range.
            5. Use `ProposeSchedulingChangesTool` when the manager asks you to create or change stores, availability, shifts, assignments, unassignments, safe deletions, or auto-fill. This creates a pending proposal only; the manager must review and apply it.

            CRITICAL RULES:
            - Never make up information. If a query requires data that you do not have, use the tools to fetch it.
            - Never claim that a proposed change has already been applied. Tell the manager to review and apply the proposal.
            - Do not propose deleting stores or employees.
            - When referring to dates, use the format YYYY-MM-DD.
            - Keep your answers helpful, professional, and clear. Use tables or lists to format data where appropriate.
            INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array<\Laravel\Ai\Contracts\Tool>
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
