<?php

declare(strict_types=1);

use App\Enums\ShiftSourceEnum;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\ScheduleFactory;
use Database\Factories\ShiftRequirementFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 09:00:00'));
});

\afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{0: User, 1: Store, 2: EmployeeProfile}
 */
function employeeShowSetup(): array
{
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'Riverside Cafe']), Store::class);
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne([
        'name' => 'Anna Novak',
        'role_label' => 'Barista',
        'hourly_rate' => 180,
        'max_hours_per_week' => 40,
        'is_active' => true,
    ]), EmployeeProfile::class);

    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);
    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return [$manager, $store, $employee];
}

\test('employee show renders enriched payload with stats and upcoming shifts', function (): void {
    [$manager, $store, $employee] = \employeeShowSetup();

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'name' => 'Riverside - June 2026',
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => 'published',
        'published_at' => \now(),
    ]), Schedule::class);

    $shift = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-20',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'role_label' => 'Barista',
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    \app(AssignmentService::class)->assign($shift, $employee, $manager);

    $response = $this->be($manager, 'users')->get('/employees/show?id=' . $employee->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'employees/Show');
    $response->assertJsonPath('props.employee.name', 'Anna Novak');
    $response->assertJsonPath('props.employee.role_label', 'Barista');
    $response->assertJsonPath('props.employee.hourly_rate', 180);
    $response->assertJsonPath('props.employee.is_active', true);
    $response->assertJsonPath('props.employee.has_login', false);
    $response->assertJsonPath('props.employee.login', null);

    $response->assertJsonPath('props.stats.upcoming_shifts', 1);
    $response->assertJsonPath('props.stats.hours_this_week', 8);
    $response->assertJsonPath('props.stats.hours_this_month', 8);
    $response->assertJsonPath('props.stats.hours_total', 8);
    $response->assertJsonPath('props.stats.conflicts', 1);

    $response->assertJsonPath('props.upcoming_shifts.0.id', $shift->getKey());
    $response->assertJsonPath('props.upcoming_shifts.0.date', '2026-06-20');
    $response->assertJsonPath('props.upcoming_shifts.0.start_time', '08:00:00');
    $response->assertJsonPath('props.upcoming_shifts.0.end_time', '16:00:00');
    $response->assertJsonPath('props.upcoming_shifts.0.role_label', 'Barista');
    $response->assertJsonPath('props.upcoming_shifts.0.schedule_id', $schedule->getKey());
    $response->assertJsonPath('props.upcoming_shifts.0.store_id', $store->getKey());
    $response->assertJsonPath('props.upcoming_shifts.0.store_name', 'Riverside Cafe');

    $response->assertJsonPath('props.stores.0.id', $store->getKey());
    $response->assertJsonPath('props.stores.0.name', 'Riverside Cafe');

    $response->assertJsonCount(7, 'props.availability');
    $response->assertJsonPath('props.availability.0.date', '2026-06-15');
    $response->assertJsonPath('props.availability.0.has_unavailable_entry', false);
});

\test('employee show reports conflict count when an employee has overlapping shifts', function (): void {
    [$manager, $store, $employee] = \employeeShowSetup();

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'name' => 'Riverside - July 2026',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'status' => 'draft',
    ]), Schedule::class);

    $first = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-07-10',
        'start_time' => '10:00:00',
        'end_time' => '14:00:00',
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    $second = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-07-10',
        'start_time' => '13:00:00',
        'end_time' => '17:00:00',
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    \app(AssignmentService::class)->assign($first, $employee, $manager);
    \app(AssignmentService::class)->assign($second, $employee, $manager);

    $response = $this->be($manager, 'users')->get('/employees/show?id=' . $employee->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'employees/Show');
    $response->assertJsonPath('props.stats.conflicts', 4);
});

\test('employee show marks availability days with unavailable entries', function (): void {
    [$manager, $store, $employee] = \employeeShowSetup();

    DB::table('employee_availabilities')->insert([
        'employee_profile_id' => $employee->getKey(),
        'date' => '2026-06-18',
        'type' => 'unavailable',
        'source' => 'employee',
        'created_by' => $employee->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->get('/employees/show?id=' . $employee->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'employees/Show');
    $response->assertJsonPath('props.availability.0.date', '2026-06-15');
    $response->assertJsonPath('props.availability.0.has_unavailable_entry', false);
    $response->assertJsonPath('props.availability.3.date', '2026-06-18');
    $response->assertJsonPath('props.availability.3.has_unavailable_entry', true);
    $response->assertJsonPath('props.availability.3.weekday', 'thu');
});

\test('employee show excludes cancelled assignments from hours totals', function (): void {
    [$manager, $store, $employee] = \employeeShowSetup();

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'name' => 'Riverside - June 2026',
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => 'published',
        'published_at' => \now(),
    ]), Schedule::class);

    $kept = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-22',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    $cancelled = Typer::assertInstance(ShiftRequirementFactory::new()->createOne([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-23',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ]), ShiftRequirement::class);

    \app(AssignmentService::class)->assign($kept, $employee, $manager);
    \app(AssignmentService::class)->assign($cancelled, $employee, $manager);

    $cancelledAssignment = Typer::assertInstance(
        ShiftAssignment::query()->where('shift_requirement_id', $cancelled->getKey())->first(),
        ShiftAssignment::class,
    );
    $cancelledAssignment->forceFill(['status' => 'cancelled'])->save();

    $response = $this->be($manager, 'users')->get('/employees/show?id=' . $employee->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.stats.upcoming_shifts', 2);
    $response->assertJsonPath('props.stats.hours_this_month', 4);
});
