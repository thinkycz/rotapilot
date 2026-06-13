<?php

declare(strict_types=1);

use App\Enums\AvailabilitySourceEnum;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\ScheduleFactory;
use Database\Factories\ShiftAssignmentFactory;
use Database\Factories\ShiftRequirementFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @return array{0: User, 1: Store}
 */
function modalScopedFlashWorkspace(): array
{
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return [$manager, $store];
}

function modalScopedFlashEmployee(Store $store): EmployeeProfile
{
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return $employee;
}

function modalScopedFlashSchedule(User $manager, Store $store): Schedule
{
    return Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'period_start' => '2026-06-15',
        'period_end' => '2026-06-21',
        'created_by' => $manager->getKey(),
    ]), Schedule::class);
}

function modalScopedFlashShift(User $manager, Store $store, Schedule $schedule): ShiftRequirement
{
    return Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);
}

function modalScopedFlashAvailability(User $manager, Store $store, EmployeeProfile $employee): EmployeeAvailability
{
    $availability = new EmployeeAvailability();
    $availability->forceFill([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'type' => 'available',
        'note' => null,
        'source' => AvailabilitySourceEnum::Manager->value,
        'created_by' => $manager->getKey(),
    ])->save();

    return $availability;
}

\test('shift assignment store flashes inside the shift modal', function (): void {
    [$manager, $store] = \modalScopedFlashWorkspace();
    $schedule = \modalScopedFlashSchedule($manager, $store);
    $shift = \modalScopedFlashShift($manager, $store, $schedule);
    $employee = \modalScopedFlashEmployee($store);

    $response = $this->be($manager, 'users')->post('/shift-assignments/store', [
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '08:00',
        'end_time' => '16:00',
    ], $this->inertiaHeaders());

    $response->assertRedirect();
    $response->assertSessionHas('shift_modal_success', \__('Employee assigned.'));
    $response->assertSessionMissing('success');
});

\test('shift assignment destroy flashes inside the shift modal', function (): void {
    [$manager, $store] = \modalScopedFlashWorkspace();
    $schedule = \modalScopedFlashSchedule($manager, $store);
    $shift = \modalScopedFlashShift($manager, $store, $schedule);
    $employee = \modalScopedFlashEmployee($store);
    $assignment = Typer::assertInstance(ShiftAssignmentFactory::new()->createOne([
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '08:00',
        'end_time' => '16:00',
        'assigned_by' => $manager->getKey(),
    ]), ShiftAssignment::class);

    $response = $this->be($manager, 'users')->post('/shift-assignments/destroy', [
        'id' => $assignment->getKey(),
    ], $this->inertiaHeaders());

    $response->assertRedirect();
    $response->assertSessionHas('shift_modal_success', \__('Assignment removed.'));
    $response->assertSessionMissing('success');
});

\test('shift auto-fill flashes scoped success and error messages', function (): void {
    [$manager, $store] = \modalScopedFlashWorkspace();
    $schedule = \modalScopedFlashSchedule($manager, $store);
    $shift = \modalScopedFlashShift($manager, $store, $schedule);
    $employee = \modalScopedFlashEmployee($store);
    \modalScopedFlashAvailability($manager, $store, $employee);

    $success = $this->be($manager, 'users')->post('/shift-requirements/auto-fill', [
        'shift_requirement_id' => $shift->getKey(),
    ], $this->inertiaHeaders());

    $success->assertRedirect();
    $success->assertSessionHas('shift_modal_success', \__('Shift auto-filled.'));
    $success->assertSessionMissing('success');

    $emptyShift = \modalScopedFlashShift($manager, $store, $schedule);

    $warning = $this->be($manager, 'users')->post('/shift-requirements/auto-fill', [
        'shift_requirement_id' => $emptyShift->getKey(),
    ], $this->inertiaHeaders());

    $warning->assertRedirect();
    $warning->assertSessionHas('shift_modal_error', \__('No eligible employees found for auto-fill.'));
    $warning->assertSessionMissing('error');
});

\test('shift requirement store and destroy use modal scoped flash keys', function (): void {
    [$manager, $store] = \modalScopedFlashWorkspace();
    $schedule = \modalScopedFlashSchedule($manager, $store);
    $shift = \modalScopedFlashShift($manager, $store, $schedule);

    $created = $this->be($manager, 'users')->post('/shift-requirements/store?schedule_id=' . $schedule->getKey(), [
        'date' => '2026-06-17',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'role_label' => 'Barista',
        'note' => null,
    ], $this->inertiaHeaders());

    $created->assertRedirect();
    $created->assertSessionHas('create_shift_modal_success', \__('Shift created.'));
    $created->assertSessionMissing('success');

    $destroyed = $this->be($manager, 'users')->post('/shift-requirements/destroy', [
        'id' => $shift->getKey(),
    ], $this->inertiaHeaders());

    $destroyed->assertRedirect();
    $destroyed->assertSessionHas('shift_modal_success', \__('Shift removed.'));
    $destroyed->assertSessionMissing('success');
});

\test('availability store update and destroy use modal scoped flash keys', function (): void {
    [$manager, $store] = \modalScopedFlashWorkspace();
    $employee = \modalScopedFlashEmployee($store);
    $availability = \modalScopedFlashAvailability($manager, $store, $employee);

    $stored = $this->be($manager, 'users')->post('/availability/store', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-17',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'type' => 'available',
        'note' => null,
    ], $this->inertiaHeaders());

    $stored->assertRedirect();
    $stored->assertSessionHas('availability_modal_success', \__('Availability added.'));
    $stored->assertSessionMissing('success');

    $updated = $this->be($manager, 'users')->post('/availability/update?id=' . $availability->getKey(), [
        'start_time' => '10:00',
        'end_time' => '18:00',
        'type' => 'available',
        'note' => 'Updated',
    ], $this->inertiaHeaders());

    $updated->assertRedirect();
    $updated->assertSessionHas('availability_modal_success', \__('Availability updated.'));
    $updated->assertSessionMissing('success');

    $destroyed = $this->be($manager, 'users')->post('/availability/destroy?id=' . $availability->getKey(), [], $this->inertiaHeaders());

    $destroyed->assertRedirect();
    $destroyed->assertSessionHas('availability_modal_success', \__('Availability removed.'));
    $destroyed->assertSessionMissing('success');
});

\test('availability manual validation errors use modal scoped flash keys', function (): void {
    [$manager, $store] = \modalScopedFlashWorkspace();
    $employee = \modalScopedFlashEmployee($store);
    $availability = \modalScopedFlashAvailability($manager, $store, $employee);

    $stored = $this->be($manager, 'users')->post('/availability/store', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-17',
        'start_time' => '',
        'end_time' => '',
        'type' => 'available',
        'note' => null,
    ], $this->inertiaHeaders());

    $stored->assertRedirect();
    $stored->assertSessionHas('availability_modal_error', \__('Available/backup days need start and end times.'));
    $stored->assertSessionMissing('error');

    $updated = $this->be($manager, 'users')->post('/availability/update?id=' . $availability->getKey(), [
        'start_time' => '',
        'end_time' => '',
        'type' => 'available',
        'note' => null,
    ], $this->inertiaHeaders());

    $updated->assertRedirect();
    $updated->assertSessionHas('availability_modal_error', \__('Available/backup days need start and end times.'));
    $updated->assertSessionMissing('error');
});
