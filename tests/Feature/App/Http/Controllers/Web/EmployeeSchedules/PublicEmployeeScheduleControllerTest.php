<?php

declare(strict_types=1);

use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\ScheduleFactory;
use Database\Factories\ShiftAssignmentFactory;
use Database\Factories\ShiftRequirementFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-13 12:00:00'));
});

\afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{0: User, 1: Store}
 */
function publicScheduleManagerAndStore(string $storeName = 'Downtown Cafe'): array
{
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => 'store_manager',
    ]), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne([
        'name' => $storeName,
    ]), Store::class);

    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return [$manager, $store];
}

function publicScheduleEmployee(Store $store): EmployeeProfile
{
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne([
        'name' => 'Anna Novak',
    ]), EmployeeProfile::class);

    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return $employee;
}

function publicScheduleRow(Store $store, string $name, string $periodStart, string $status = 'published'): Schedule
{
    return Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'name' => $name,
        'period_start' => $periodStart,
        'period_end' => CarbonImmutable::parse($periodStart)->endOfMonth()->format('Y-m-d'),
        'status' => $status,
        'published_at' => $status === 'published' ? \now() : null,
    ]), Schedule::class);
}

\test('public employee schedule page is visible without authentication', function (): void {
    [, $store] = \publicScheduleManagerAndStore();
    $employee = \publicScheduleEmployee($store);
    $schedule = \publicScheduleRow($store, 'Downtown Cafe - June 2026', '2026-06-01');
    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]), ShiftRequirement::class);
    Typer::assertInstance(ShiftAssignmentFactory::new()->createOne([
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '08:00',
        'end_time' => '16:00',
        'status' => 'confirmed',
    ]), ShiftAssignment::class);

    $response = $this->get('/public/employee-schedules?token=' . $employee->ensurePublicScheduleToken(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'public/EmployeeSchedules');
    $response->assertJsonPath('props.employee.name', 'Anna Novak');
    $response->assertJsonPath('props.selected_store_id', $store->getKey());
    $response->assertJsonPath('props.selected_schedule.id', $schedule->getKey());
    $response->assertJsonPath('props.days.2026-06-16.shifts.0.assignments.0.employee_name', 'Anna Novak');
});

\test('public employee schedule page rejects invalid tokens', function (): void {
    $response = $this->get('/public/employee-schedules?token=missing', $this->inertiaHeaders());

    $response->assertNotFound();
});

\test('public employee schedule page exposes only assigned stores and falls back from foreign store selection', function (): void {
    [, $assignedStore] = \publicScheduleManagerAndStore('Assigned Cafe');
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne([
        'name' => 'Foreign Cafe',
    ]), Store::class);
    $employee = \publicScheduleEmployee($assignedStore);
    \publicScheduleRow($assignedStore, 'Assigned Cafe - June 2026', '2026-06-01');
    \publicScheduleRow($foreignStore, 'Foreign Cafe - June 2026', '2026-06-01');

    $response = $this->get(
        '/public/employee-schedules?token=' . $employee->ensurePublicScheduleToken() . '&store_id=' . $foreignStore->getKey(),
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonPath('props.selected_store_id', $assignedStore->getKey());
    $response->assertJsonCount(1, 'props.stores');
    $response->assertJsonPath('props.stores.0.name', 'Assigned Cafe');
    $response->assertJsonPath('props.schedules.0.name', 'Assigned Cafe - June 2026');
});

\test('public employee schedule page includes only published current and future schedules', function (): void {
    [, $store] = \publicScheduleManagerAndStore();
    $employee = \publicScheduleEmployee($store);
    $current = \publicScheduleRow($store, 'Downtown Cafe - June 2026', '2026-06-01');
    $future = \publicScheduleRow($store, 'Downtown Cafe - July 2026', '2026-07-01');
    \publicScheduleRow($store, 'Downtown Cafe - May 2026', '2026-05-01');
    // Past draft / past archived schedules on different periods to
    // exercise the status filter without colliding with the unique
    // (store_id, period_start) index.
    \publicScheduleRow($store, 'Downtown Cafe - Draft April 2026', '2026-04-01', 'draft');
    \publicScheduleRow($store, 'Downtown Cafe - Archived March 2026', '2026-03-01', 'archived');

    $response = $this->get('/public/employee-schedules?token=' . $employee->ensurePublicScheduleToken(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonCount(2, 'props.schedules');
    $response->assertJsonPath('props.schedules.0.id', $current->getKey());
    $response->assertJsonPath('props.schedules.1.id', $future->getKey());
});

\test('employee detail includes the public schedule url for manager access', function (): void {
    [$manager, $store] = \publicScheduleManagerAndStore();
    $employee = \publicScheduleEmployee($store);

    $response = $this->be($manager, 'users')->get('/employees/show?id=' . $employee->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath(
        'props.employee.public_schedule_url',
        '/public/employee-schedules?token=' . $employee->ensurePublicScheduleToken(),
    );
});

\test('schedule create and update generate titles with store name month and year', function (): void {
    [$manager, $store] = \publicScheduleManagerAndStore('Downtown Cafe');

    $created = $this->be($manager, 'users')->post('/schedules/store', [
        'store_id' => $store->getKey(),
        'month' => 6,
        'year' => 2026,
    ], $this->inertiaHeaders());

    $created->assertRedirect();
    $schedule = Schedule::query()
        ->where('store_id', $store->getKey())
        ->whereDate('period_start', '2026-06-01')
        ->first();
    $this->assertNotNull($schedule);
    $this->assertSame('Downtown Cafe - June 2026', $schedule->getName());

    $updated = $this->be($manager, 'users')->post('/schedules/update?id=' . $schedule->getKey(), [
        'month' => 7,
        'year' => 2026,
    ], $this->inertiaHeaders());

    $updated->assertRedirect('/schedules/show?id=' . $schedule->getKey());
    $schedule->refresh();
    $this->assertSame('Downtown Cafe - July 2026', $schedule->getName());
});
