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
        return 'Create a pending batch proposal for manager-confirmed scheduling changes. This tool does not directly modify stores, availability, shifts, or assignments.';
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
                        ->description('One of store.create, store.update, availability.create, availability.update, availability.delete, shift.create, shift.update, shift.delete, shift.assign, shift.unassign, shift.autofill.')
                        ->required(),
                ]))
                ->description('Array of action objects. Include the explicit fields needed by each action type.')
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
