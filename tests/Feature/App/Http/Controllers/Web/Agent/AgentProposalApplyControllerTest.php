<?php

declare(strict_types=1);

use App\Models\AgentActionProposal;
use App\Models\EmployeeAvailability;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use Database\Factories\ScheduleFactory;
use Database\Factories\ShiftRequirementFactory;
use Database\Factories\UserFactory;
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

    $this
        ->from('/agent?conversation=' . $conversation->getKey())
        ->be($manager, 'users')
        ->post('/agent/proposals/apply', [
            'proposal_id' => $proposal->getKey(),
        ], $this->inertiaHeaders())
        ->assertRedirect('/agent?conversation=' . $conversation->getKey());

    static::assertFalse(Store::query()->where('name', 'Rollback Store')->exists());
    static::assertSame(AgentActionProposal::STATUS_FAILED, Typer::assertInstance($proposal->refresh(), AgentActionProposal::class)->getStatus());
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

    $this->be($manager, 'users')->post('/agent/proposals/apply', [
        'proposal_id' => $applied->getKey(),
    ], $this->inertiaHeaders())->assertRedirect();

    static::assertSame(AgentActionProposal::STATUS_APPLIED, Typer::assertInstance($applied->refresh(), AgentActionProposal::class)->getStatus());
});
