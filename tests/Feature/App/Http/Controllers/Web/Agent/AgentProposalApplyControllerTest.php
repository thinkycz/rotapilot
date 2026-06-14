<?php

declare(strict_types=1);

use App\Ai\AgentConversationContext;
use App\Ai\Tools\ProposeSchedulingChangesTool;
use App\Models\AgentActionProposal;
use App\Models\EmployeeAvailability;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\StoreBusinessHour;
use App\Models\User;
use Database\Factories\ScheduleFactory;
use Database\Factories\ShiftAssignmentFactory;
use Database\Factories\ShiftRequirementFactory;
use Database\Factories\UserFactory;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from proposal apply', function (): void {
    $this->post('/agent/proposals/apply', [
        'proposal_id' => 1,
    ])->assertRedirect('/login');
});

\test('employee cannot apply proposal', function (): void {
    $employee = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'employee',
    ]), User::class);

    $this->be($employee, 'users')->post('/agent/proposals/apply', [
        'proposal_id' => 1,
    ], $this->inertiaHeaders())->assertForbidden();
});

\test('manager can apply own pending proposal transactionally', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
        'locale' => 'cs',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Existing Store');
    $employee = \employeeFor($store, 'Assigned Employee');
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);
    $proposal = \createAgentProposal($manager, $conversation->getKey(), [
        [
            'type' => 'store.update',
            'store_id' => $store->getKey(),
            'name' => 'Updated Store',
            'address' => 'Main 1',
            'city' => 'Prague',
            'timezone' => 'Europe/Prague',
            'is_active' => true,
        ],
        [
            'type' => 'availability.create',
            'employee_profile_id' => $employee->getKey(),
            'store_id' => $store->getKey(),
            'date' => '2026-06-20',
            'availability_type' => 'available',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'note' => 'AI proposal',
        ],
        [
            'type' => 'shift.assign',
            'shift_requirement_id' => $shift->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'start_time' => '09:00',
            'end_time' => '16:00',
        ],
    ]);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    static::assertSame('Updated Store', Typer::assertInstance(Store::query()->find($store->getKey()), Store::class)->getName());
    static::assertTrue(EmployeeAvailability::query()->where('employee_profile_id', $employee->getKey())->where('source', 'ai')->exists());
    static::assertTrue(ShiftAssignment::query()->where('shift_requirement_id', $shift->getKey())->where('employee_profile_id', $employee->getKey())->exists());
    static::assertSame(AgentActionProposal::STATUS_APPLIED, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('role', 'assistant')
        ->where('content', 'like', 'Hotovo, návrh%')
        ->exists());
    static::assertFalse(ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('content', 'like', '%internal confirmation event%')
        ->exists());
});

\test('failed proposal apply rolls back domain changes and marks failed', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $proposal = \createAgentProposal($manager, $conversation->getKey(), [
        [
            'type' => 'store.create',
            'name' => 'Rollback Store',
            'address' => null,
            'city' => null,
            'timezone' => 'Europe/Prague',
            'is_active' => true,
        ],
        [
            'type' => 'shift.assign',
            'shift_requirement_id' => 999999,
            'employee_profile_id' => 999999,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ],
    ]);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    static::assertFalse(Store::query()->where('name', 'Rollback Store')->exists());
    static::assertSame(AgentActionProposal::STATUS_FAILED, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
});

\test('manager can apply normalized shift assignment proposal with omitted times', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Existing Store');
    $employee = \employeeFor($store, 'Assigned Employee');
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    \app(AgentConversationContext::class)->setConversationId($conversation->getKey());
    $this->be($manager, 'users');

    $payload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Assign employee',
        'actions' => [
            [
                'type' => 'shift.assign',
                'shift_requirement_id' => $shift->getKey(),
                'employee_profile_id' => $employee->getKey(),
            ],
        ],
    ])));

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->post('/agent/proposals/apply', [
            'proposal_id' => $payload['proposal_id'],
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    $assignment = Typer::assertInstance(ShiftAssignment::query()
        ->where('shift_requirement_id', $shift->getKey())
        ->where('employee_profile_id', $employee->getKey())
        ->first(), ShiftAssignment::class);

    static::assertSame('10:00', $assignment->getStartTime());
    static::assertSame('18:00', $assignment->getEndTime());
});

\test('manager can apply business hours update proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Existing Store');
    $proposal = \createAgentProposal($manager, $conversation->getKey(), [
        [
            'type' => 'business_hours.update',
            'store_id' => $store->getKey(),
            'hours' => [
                [
                    'day_of_week' => 1,
                    'opens_at' => '08:00',
                    'closes_at' => '16:00',
                    'is_closed' => false,
                ],
                [
                    'day_of_week' => 7,
                    'opens_at' => null,
                    'closes_at' => null,
                    'is_closed' => true,
                ],
            ],
        ],
    ]);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    $monday = Typer::assertInstance(StoreBusinessHour::query()
        ->where('store_id', $store->getKey())
        ->where('day_of_week', 1)
        ->first(), StoreBusinessHour::class);
    $sunday = Typer::assertInstance(StoreBusinessHour::query()
        ->where('store_id', $store->getKey())
        ->where('day_of_week', 7)
        ->first(), StoreBusinessHour::class);

    static::assertSame('08:00', \mb_substr(Typer::assertString($monday->getOpensAt()), 0, 5));
    static::assertSame('16:00', \mb_substr(Typer::assertString($monday->getClosesAt()), 0, 5));
    static::assertFalse($monday->getIsClosed());
    static::assertNull($sunday->getOpensAt());
    static::assertNull($sunday->getClosesAt());
    static::assertTrue($sunday->getIsClosed());
    static::assertSame(AgentActionProposal::STATUS_APPLIED, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
});

\test('failed proposal apply rolls back business hours changes', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Existing Store');
    $proposal = \createAgentProposal($manager, $conversation->getKey(), [
        [
            'type' => 'business_hours.update',
            'store_id' => $store->getKey(),
            'hours' => [
                [
                    'day_of_week' => 1,
                    'opens_at' => '08:00',
                    'closes_at' => '16:00',
                    'is_closed' => false,
                ],
            ],
        ],
        [
            'type' => 'shift.assign',
            'shift_requirement_id' => 999999,
            'employee_profile_id' => 999999,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ],
    ]);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    static::assertFalse(StoreBusinessHour::query()->where('store_id', $store->getKey())->exists());
    static::assertSame(AgentActionProposal::STATUS_FAILED, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
});

\test('duplicate assignment start fails with domain error instead of database exception', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Existing Store');
    $employee = \employeeFor($store, 'Assigned Employee');
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'start_time' => '10:00:00',
        'end_time' => '21:00:00',
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);
    ShiftAssignmentFactory::new()->createOne([
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '10:00',
        'end_time' => '21:00',
        'status' => 'draft',
        'assigned_by' => $manager->getKey(),
    ]);
    $proposal = \createAgentProposal($manager, $conversation->getKey(), [
        [
            'type' => 'shift.assign',
            'shift_requirement_id' => $shift->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'start_time' => '10:00',
            'end_time' => '12:00',
        ],
    ]);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    $proposal = Typer::assertInstance($proposal->refresh(), AgentActionProposal::class);

    static::assertSame(AgentActionProposal::STATUS_FAILED, $proposal->getStatus());
    static::assertStringContainsString(
        'Remove the existing assignment before creating a replacement',
        Typer::assertString($proposal->getResult()['error'] ?? null),
    );
});

\test('manager cannot apply foreign or non pending proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $foreignConversation = \createAgentConversation($other);
    $foreign = \createAgentProposal($other, $foreignConversation->getKey(), []);
    $ownConversation = \createAgentConversation($manager);
    $applied = \createAgentProposal($manager, $ownConversation->getKey(), [], AgentActionProposal::STATUS_APPLIED);

    $this->be($manager, 'users')->post('/agent/proposals/apply', [
        'proposal_id' => $foreign->getKey(),
    ], $this->inertiaHeaders())->assertNotFound();

    $nonPendingResponse = $this->be($manager, 'users')->post('/agent/proposals/apply', [
        'proposal_id' => $applied->getKey(),
    ], $this->inertiaHeaders());

    $nonPendingResponse->assertRedirect('/agent?conversation=' . $ownConversation->getKey());

    static::assertSame(AgentActionProposal::STATUS_APPLIED, Typer::assertInstance($applied->refresh(), AgentActionProposal::class)->getStatus());
});

\test('manager can apply only selected actions in proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
        'locale' => 'en',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Existing Store');
    $employee = \employeeFor($store, 'Assigned Employee');
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    $proposal = \createAgentProposal($manager, $conversation->getKey(), [
        [
            'type' => 'store.update',
            'store_id' => $store->getKey(),
            'name' => 'Should Not Update',
            'address' => 'Main 1',
            'city' => 'Prague',
            'timezone' => 'Europe/Prague',
            'is_active' => true,
        ],
        [
            'type' => 'availability.create',
            'employee_profile_id' => $employee->getKey(),
            'store_id' => $store->getKey(),
            'date' => '2026-06-20',
            'availability_type' => 'available',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'note' => 'Should Create',
        ],
    ]);

    // Apply only action 1 (availability.create) and skip action 0 (store.update)
    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
            'action_indexes' => [1],
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    // Check that store was NOT updated
    static::assertSame('Existing Store', Typer::assertInstance(Store::query()->find($store->getKey()), Store::class)->getName());
    // Check that availability WAS created
    static::assertTrue(EmployeeAvailability::query()->where('employee_profile_id', $employee->getKey())->where('source', 'ai')->exists());
    // Check that proposal has status applied
    $proposal = Typer::assertInstance($proposal->refresh(), AgentActionProposal::class);
    static::assertSame(AgentActionProposal::STATUS_APPLIED, $proposal->getStatus());

    // Check result format contains action_index: 1
    $appliedActions = $proposal->getResult()['applied_actions'] ?? [];
    static::assertCount(1, $appliedActions);
    static::assertSame(1, $appliedActions[0]['action_index']);

    // Check notifier correctly counted 1 applied action
    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $conversation->getKey())
        ->where('role', 'assistant')
        ->where('content', 'like', '%Actions applied: 1%')
        ->exists());
});

\test('stale proposal apply url redirects back to agent page', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);

    $response = $this->be($manager, 'users')->get('/agent/proposals/apply');

    $response->assertRedirect('/agent');
});

\test('manager can apply shift assignment update proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
        'locale' => 'cs',
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Existing Store');
    $employee = \employeeFor($store, 'Original Employee');
    $otherEmployee = \employeeFor($store, 'New Employee');
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'start_time' => '10:00:00',
        'end_time' => '21:00:00',
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);
    $assignment = Typer::assertInstance(ShiftAssignmentFactory::new()->createOne([
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '10:00',
        'end_time' => '15:00',
        'status' => 'draft',
        'assigned_by' => $manager->getKey(),
    ]), ShiftAssignment::class);

    $proposal = \createAgentProposal($manager, $conversation->getKey(), [
        [
            'type' => 'shift.assignment.update',
            'shift_assignment_id' => $assignment->getKey(),
            'employee_profile_id' => $otherEmployee->getKey(),
            'start_time' => '12:00',
            'end_time' => '17:00',
        ],
    ]);

    $response = $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders());

    $response->assertRedirect('/agent?conversation=' . $conversation->getKey());

    $assignment->refresh();
    static::assertSame($otherEmployee->getKey(), $assignment->getEmployeeProfileId());
    static::assertSame('12:00', $assignment->getStartTime());
    static::assertSame('17:00', $assignment->getEndTime());
    static::assertSame(AgentActionProposal::STATUS_APPLIED, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
});
