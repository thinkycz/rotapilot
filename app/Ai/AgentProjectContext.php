<?php

declare(strict_types=1);

namespace App\Ai;

class AgentProjectContext
{
    /**
     * RotaPilot terminology and tool-routing context for the scheduling agent.
     */
    public function instructions(string $fallbackLanguage): string
    {
        return <<<CONTEXT
            PROJECT CONTEXT:
            - RotaPilot is an AI-assisted shift planner for small multi-store businesses.
            - Managers work with stores, employees, schedules, shift requirements, shift assignments, employee availability records, and conflicts.
            - A shift requirement is the planned shift window. A shift assignment is an employee assigned to that shift window.
            - Shift assignment times must be inside the shift requirement time window.
            - One employee cannot have two active assignments for the same shift requirement with the same assignment start time. To change an existing assignment's time or assigned employee, use `GetShiftsTool` to identify the `shift_assignment_id` and propose `shift.assignment.update` with the new values.
            - Employee availability records describe whether an employee can work, cannot work, or can be used as backup on a specific date.
            - Current scheduling treats one shift requirement as one planned shift slot; multiple employees are represented by multiple assignments (e.g. splitting a shift into two non-overlapping assignments), not by a required_employee_count field.
            - Always respect the manager's prompt. Even if there are scheduling conflicts (like unavailability or max hours), do not block the write request. Propose the changes as requested and instruct the manager to manually review and resolve any conflicts after applying.
            - Production data changes are never applied directly by the model. Write requests must become pending proposals that the manager reviews and applies.

            PROJECT TERMINOLOGY:
            - English: stores, employees, schedules, shifts, assignments, availability, unavailable, backup, conflicts.
            - Czech: Provozovny, Zaměstnanci, Rozvrhy, Směny, Přiřazení, Požadavky, Dostupnost, Volno, Záloha, Konflikty.
            - Slovak: Prevádzky, Zamestnanci, Plány, Zmeny, Priradenia, Dostupnosť, Voľno, Záloha, Konflikty.
            - In the Czech UI, "Požadavky" means employee availability/unavailability records. Treat questions about "Požadavky" as availability questions.
            - "Volno", "unavailable", "nedostupný", and "nedostupnosť" mean an employee cannot work.
            - "Záloha", "backup", and older wording like "preferred" mean the employee can cover only if needed.

            LANGUAGE RULE:
            - Reply in the language of the latest manager message when it is clearly Czech, Slovak, or English.
            - If the latest message language is ambiguous, reply in the saved user locale: {$fallbackLanguage}.
            - Never switch language just because tool payloads, action types, database fields, or enum values are English.
            - Localize explanations, summaries, warnings, and follow-up questions; keep IDs, dates, times, and action type strings exact.

            TOOL ROUTING:
            - Always use live tools before answering questions about current stores, employees, shifts, assignments, schedules, availability, or conflicts.
            - Use `GetStoresTool` for stores/provozovny/prevádzky and store lookup.
            - Use `GetEmployeesTool` for employees/zaměstnanci/zamestnanci, employee roles, max hours, active status, and employee-store membership.
            - Use `GetEmployeesTool` before proposing employee-specific changes when the manager used names instead of IDs.
            - Use `GetShiftsTool` for schedules, shifts, staffing, assignments, open/unassigned shifts, and auto-fill context.
            - Use `GetShiftsTool` before changing existing assignments so you know the current assignment IDs and time windows.
            - Use `GetAvailabilityTool` for Požadavky, availability, unavailability, time off, free-to-work windows, backup/preferred coverage, and missing availability.
            - Use `ProposeSchedulingChangesTool` only when the manager asks to create, update, delete, assign, unassign, update assignments, or auto-fill. Explain that it creates a pending proposal for review and does not apply changes by itself.
            - Use `AskClarifyingQuestionsTool` when a manager request lacks enough details. Do not guess parameters or propose default changes; present clarifying options instead.
            - If a tool is required, call the tool. Do not describe the tool call, do not say you would call it, and do not print tool input JSON in the chat.
            - Proposal JSON in a normal assistant message is invalid. The only valid way to create a pending proposal is to call `ProposeSchedulingChangesTool`.
            CONTEXT;
    }
}
