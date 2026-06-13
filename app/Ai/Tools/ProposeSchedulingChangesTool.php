<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AgentActionProposalSerializer;
use App\Ai\AgentConversationContext;
use App\Ai\AgentProposalBuilder;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class ProposeSchedulingChangesTool implements Tool
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly AgentConversationContext $context,
        private readonly AgentProposalBuilder $builder,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Create a real pending batch proposal for manager-confirmed scheduling changes. This tool does not directly modify stores, availability/Požadavky, shifts, or assignments. Assignment times must be inside the shift window. To replace an existing assignment time, include shift.unassign for the current assignment_id before shift.assign. Never print proposal action JSON in chat; call this tool instead.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('Short manager-facing summary of the proposed batch.')
                ->required(),
            'actions' => $schema->array()
                ->items($schema->object([
                    'type' => $schema->string()
                        ->description(
                            'Action type. One of: store.create, store.update, ' .
                            'availability.create, availability.update, availability.delete, ' .
                            'shift.create, shift.update, shift.delete, shift.assign, shift.unassign, shift.autofill.',
                        )
                        ->required(),
                    'availability_type' => $schema->string()
                        ->description(
                            'Required for availability.create and availability.update. ' .
                            'Must be one of: available, unavailable, backup. ' .
                            'Use "available" when the employee is free/can work. ' .
                            'Use "unavailable" when the employee cannot work. ' .
                            'Use "backup" when the employee can only cover if needed.',
                        ),
                ]))
                ->description(
                    'Array of action objects. Each object must contain "type" and any additional ' .
                    'fields required by that action type. Required fields: ' .
                    'store.create: name, timezone, optional address, city, is_active. ' .
                    'store.update: store_id, name, timezone, optional address, city, is_active. ' .
                    'availability.create: employee_profile_id, date, availability_type, optional store_id, start_time, end_time, note. ' .
                    'availability.update: availability_id, availability_type, optional start_time, end_time, note. ' .
                    'availability.delete: availability_id. ' .
                    'shift.create: schedule_id, date, start_time, end_time, optional role_label, note, employee_profile_ids. ' .
                    'shift.update: shift_requirement_id, date, start_time, end_time, optional role_label, note. ' .
                    'shift.delete and shift.autofill: shift_requirement_id. ' .
                    'shift.assign: shift_requirement_id, employee_profile_id, optional start_time and end_time; omitted times default to the shift window. Assignment times must be inside the shift window and cannot duplicate an active assignment with the same shift_requirement_id, employee_profile_id, and start_time unless that existing assignment is unassigned earlier in this same proposal. ' .
                    'shift.unassign: shift_assignment_id.',
                )
                ->required(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $conversationId = $this->context->conversationId();

            if ($conversationId === null) {
                return $this->json(['error' => 'No active conversation is available for this proposal.']);
            }

            $actions = $request['actions'] ?? [];
            if (!\is_array($actions)) {
                return $this->json(['error' => 'Actions must be an array.']);
            }

            $rawActions = \array_values($actions);

            $proposal = $this->builder->create(
                User::mustAuth(),
                $conversationId,
                Typer::assertString($request['summary'] ?? null),
                $rawActions,
            );

            return $this->json([
                'proposal_id' => $proposal->getKey(),
                'status' => $proposal->getStatus(),
                'summary' => $proposal->getSummary(),
                'action_count' => \count($proposal->getActions()),
                'actions' => AgentActionProposalSerializer::summarizeActions($proposal->getActions()),
            ]);
        } catch (Throwable $throwable) {
            return $this->json(['error' => $throwable->getMessage()]);
        }
    }

    /**
     * Encode tool response.
     *
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        $json = \json_encode($payload);

        return $json === false ? '{"error":"Unable to encode proposal response."}' : $json;
    }
}
