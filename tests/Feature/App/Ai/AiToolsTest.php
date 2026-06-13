<?php

declare(strict_types=1);

use App\Ai\AgentConversationContext;
use App\Ai\Tools\GetAvailabilityTool;
use App\Ai\Tools\GetEmployeesTool;
use App\Ai\Tools\GetShiftsTool;
use App\Ai\Tools\GetStoresTool;
use App\Ai\Tools\ProposeSchedulingChangesTool;
use App\Enums\UserRoleEnum;
use App\Models\AgentActionProposal;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use Database\Factories\ScheduleFactory;
use Database\Factories\ShiftAssignmentFactory;
use Database\Factories\ShiftRequirementFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('tool descriptions include project terminology aliases', function (): void {
    static::assertStringContainsString('Provozovny', (new GetStoresTool())->description());
    static::assertStringContainsString('Zaměstnanci', (new GetEmployeesTool())->description());
    static::assertStringContainsString('Směny', (new GetShiftsTool())->description());
    static::assertStringContainsString('Požadavky', (new GetAvailabilityTool())->description());
    static::assertStringContainsString('availability/Požadavky', \app(ProposeSchedulingChangesTool::class)->description());
    static::assertStringContainsString('include shift.unassign', \app(ProposeSchedulingChangesTool::class)->description());
    static::assertStringContainsString('Never print proposal action JSON in chat', \app(ProposeSchedulingChangesTool::class)->description());
});

\test('store tool returns only stores managed by authenticated manager', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    \managedStoreFor($manager, 'Owned Store');
    StoreFactory::new()->createOne(['name' => 'Foreign Store']);

    $this->be($manager, 'users');

    $payload = \decodeToolJson((new GetStoresTool())->handle(new Request()));

    static::assertSame(['Owned Store'], \array_column($payload, 'name'));
});

\test('employee tool is scoped and excludes private contact and pay fields', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $store = \managedStoreFor($manager, 'Owned Store');
    \employeeFor($store, 'Owned Employee');
    \employeeFor(Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'Foreign Store']), Store::class), 'Foreign Employee');

    $this->be($manager, 'users');

    $payload = \decodeToolJson((new GetEmployeesTool())->handle(new Request()));

    static::assertSame(['Owned Employee'], \array_column($payload, 'name'));
    static::assertArrayNotHasKey('email', $payload[0]);
    static::assertArrayNotHasKey('phone', $payload[0]);
    static::assertArrayNotHasKey('hourly_rate', $payload[0]);
});

\test('employee tool rejects foreign store filter', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    $this->be($manager, 'users');

    $payload = \decodeToolJson((new GetEmployeesTool())->handle(new Request([
        'store_id' => (string) $foreignStore->getKey(),
    ])));

    static::assertSame('You do not have permission to access store ID ' . $foreignStore->getKey(), $payload['error']);
});

\test('shift tool serializes only managed store shifts and assignments', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $store = \managedStoreFor($manager, 'Owned Store');
    $employee = \employeeFor($store, 'Owned Employee');
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
    $requirement = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-15',
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);
    ShiftAssignmentFactory::new()->createOne([
        'shift_requirement_id' => $requirement->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'status' => 'confirmed',
        'assigned_by' => $manager->getKey(),
    ]);
    ShiftRequirementFactory::new()->createOne([
        'date' => '2026-06-15',
    ]);

    $this->be($manager, 'users');

    $payload = \decodeToolJson((new GetShiftsTool())->handle(new Request([
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-15',
    ])));

    static::assertCount(1, $payload);
    static::assertSame('Owned Store', $payload[0]['store']['name']);
    static::assertSame($schedule->getKey(), $payload[0]['schedule_id']);
    static::assertSame($requirement->getKey(), $payload[0]['id']);
    static::assertArrayHasKey('assignment_id', $payload[0]['assigned_employees'][0]);
    static::assertArrayHasKey('start_time', $payload[0]['assigned_employees'][0]);
    static::assertArrayHasKey('end_time', $payload[0]['assigned_employees'][0]);
    static::assertSame('Owned Employee', $payload[0]['assigned_employees'][0]['name']);
    static::assertSame('confirmed', $payload[0]['fill_status']);
});

\test('shift tool rejects foreign store filter', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    $this->be($manager, 'users');

    $payload = \decodeToolJson((new GetShiftsTool())->handle(new Request([
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-15',
        'store_id' => (string) $foreignStore->getKey(),
    ])));

    static::assertSame('You do not have permission to access store ID ' . $foreignStore->getKey(), $payload['error']);
});

\test('availability tool serializes only managed employees and rejects foreign employee filter', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $store = \managedStoreFor($manager, 'Owned Store');
    $employee = \employeeFor($store, 'Owned Employee');
    $foreignEmployee = \employeeFor(Typer::assertInstance(StoreFactory::new()->createOne(), Store::class), 'Foreign Employee');

    DB::table('employee_availabilities')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-16',
        'start_time' => '08:00',
        'end_time' => '12:00',
        'type' => 'available',
        'note' => 'Morning only',
        'source' => 'manager',
        'created_by' => $manager->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $this->be($manager, 'users');

    $payload = \decodeToolJson((new GetAvailabilityTool())->handle(new Request([
        'start_date' => '2026-06-16',
        'end_date' => '2026-06-16',
    ])));

    static::assertCount(1, $payload);
    static::assertSame('Owned Employee', $payload[0]['employee']['name']);

    $errorPayload = \decodeToolJson((new GetAvailabilityTool())->handle(new Request([
        'start_date' => '2026-06-16',
        'end_date' => '2026-06-16',
        'employee_profile_id' => (string) $foreignEmployee->getKey(),
    ])));

    static::assertSame('You do not have permission to access employee profile ID ' . $foreignEmployee->getKey(), $errorPayload['error']);
});

\test('proposal tool creates pending proposal without mutating domain records', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    \app(AgentConversationContext::class)->setConversationId($conversation->getKey());
    $this->be($manager, 'users');

    $payload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Create a new store',
        'actions' => [
            [
                'type' => 'store.create',
                'name' => 'AI Store',
                'address' => null,
                'city' => null,
                'timezone' => 'Europe/Prague',
                'is_active' => true,
            ],
        ],
    ])));

    static::assertSame('pending', $payload['status']);
    static::assertSame(1, $payload['action_count']);
    static::assertTrue(AgentActionProposal::query()->where('summary', 'Create a new store')->exists());
    static::assertFalse(Store::query()->where('name', 'AI Store')->exists());
});

\test('proposal tool rejects foreign references safely', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    \app(AgentConversationContext::class)->setConversationId($conversation->getKey());
    $this->be($manager, 'users');

    $payload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Update foreign store',
        'actions' => [
            [
                'type' => 'store.update',
                'store_id' => $foreignStore->getKey(),
                'name' => 'Should Not Update',
                'address' => null,
                'city' => null,
                'timezone' => 'Europe/Prague',
                'is_active' => true,
            ],
        ],
    ])));

    static::assertArrayHasKey('error', $payload);
    static::assertFalse(AgentActionProposal::query()->where('summary', 'Update foreign store')->exists());
});

\test('proposal tool rejects missing or invalid availability type without creating proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Owned Store');
    $employee = \employeeFor($store, 'Owned Employee');

    \app(AgentConversationContext::class)->setConversationId($conversation->getKey());
    $this->be($manager, 'users');

    $missingPayload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Create missing availability type',
        'actions' => [
            [
                'type' => 'availability.create',
                'employee_profile_id' => $employee->getKey(),
                'store_id' => $store->getKey(),
                'date' => '2026-06-20',
                'start_time' => '08:00',
                'end_time' => '12:00',
            ],
        ],
    ])));

    static::assertSame('availability_type must be one of: available, unavailable, backup.', $missingPayload['error']);

    $invalidPayload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Create invalid availability type',
        'actions' => [
            [
                'type' => 'availability.create',
                'employee_profile_id' => $employee->getKey(),
                'store_id' => $store->getKey(),
                'date' => '2026-06-20',
                'availability_type' => 'availability.create',
                'start_time' => '08:00',
                'end_time' => '12:00',
            ],
        ],
    ])));

    static::assertSame('availability_type must be one of: available, unavailable, backup.', $invalidPayload['error']);
    static::assertFalse(AgentActionProposal::query()->whereIn('summary', [
        'Create missing availability type',
        'Create invalid availability type',
    ])->exists());
});

\test('proposal tool defaults omitted shift assignment times to the shift window', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Owned Store');
    $employee = \employeeFor($store, 'Owned Employee');
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    \app(AgentConversationContext::class)->setConversationId($conversation->getKey());
    $this->be($manager, 'users');

    $payload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Assign employee to shift',
        'actions' => [
            [
                'type' => 'shift.assign',
                'shift_requirement_id' => $shift->getKey(),
                'employee_profile_id' => $employee->getKey(),
            ],
        ],
    ])));

    static::assertSame('pending', $payload['status']);

    $proposal = Typer::assertInstance(AgentActionProposal::query()->find($payload['proposal_id']), AgentActionProposal::class);
    $actions = $proposal->getActions();

    static::assertSame('09:00', $actions[0]['start_time']);
    static::assertSame('17:00', $actions[0]['end_time']);
    static::assertFalse(ShiftAssignment::query()->where('shift_requirement_id', $shift->getKey())->exists());
});

\test('proposal tool rejects shift assignments outside the shift window without creating proposal', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Owned Store');
    $employee = \employeeFor($store, 'Owned Employee');
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

    \app(AgentConversationContext::class)->setConversationId($conversation->getKey());
    $this->be($manager, 'users');

    $payload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Assign employee outside shift window',
        'actions' => [
            [
                'type' => 'shift.assign',
                'shift_requirement_id' => $shift->getKey(),
                'employee_profile_id' => $employee->getKey(),
                'start_time' => '08:00',
                'end_time' => '12:00',
            ],
        ],
    ])));

    static::assertSame('Assignment time must be within the shift hours.', $payload['error']);
    static::assertFalse(AgentActionProposal::query()->where('summary', 'Assign employee outside shift window')->exists());
});

\test('proposal tool rejects duplicate assignment starts unless the existing assignment is unassigned first', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);
    $store = \managedStoreFor($manager, 'Owned Store');
    $employee = \employeeFor($store, 'Owned Employee');
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
        'end_time' => '21:00',
        'status' => 'draft',
        'assigned_by' => $manager->getKey(),
    ]), ShiftAssignment::class);

    \app(AgentConversationContext::class)->setConversationId($conversation->getKey());
    $this->be($manager, 'users');

    $duplicatePayload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Duplicate assignment start',
        'actions' => [
            [
                'type' => 'shift.assign',
                'shift_requirement_id' => $shift->getKey(),
                'employee_profile_id' => $employee->getKey(),
                'start_time' => '10:00',
                'end_time' => '12:00',
            ],
        ],
    ])));

    static::assertStringContainsString('already has assignment ID ' . $assignment->getKey(), Typer::assertString($duplicatePayload['error']));
    static::assertFalse(AgentActionProposal::query()->where('summary', 'Duplicate assignment start')->exists());

    $replacementPayload = \decodeToolJson(\app(ProposeSchedulingChangesTool::class)->handle(new Request([
        'summary' => 'Replace assignment start',
        'actions' => [
            [
                'type' => 'shift.unassign',
                'shift_assignment_id' => $assignment->getKey(),
            ],
            [
                'type' => 'shift.assign',
                'shift_requirement_id' => $shift->getKey(),
                'employee_profile_id' => $employee->getKey(),
                'start_time' => '10:00',
                'end_time' => '12:00',
            ],
        ],
    ])));

    static::assertSame('pending', $replacementPayload['status']);

    $proposal = Typer::assertInstance(AgentActionProposal::query()->find($replacementPayload['proposal_id']), AgentActionProposal::class);
    $actions = $proposal->getActions();

    static::assertSame('shift.unassign', $actions[0]['type']);
    static::assertSame($assignment->getKey(), $actions[0]['shift_assignment_id']);
    static::assertSame('shift.assign', $actions[1]['type']);
    static::assertSame('10:00', $actions[1]['start_time']);
    static::assertSame('12:00', $actions[1]['end_time']);
});
