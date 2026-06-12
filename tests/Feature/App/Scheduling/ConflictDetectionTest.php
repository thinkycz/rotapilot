<?php

declare(strict_types=1);

use App\Enums\ShiftSourceEnum;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ScheduleConflict;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Services\Scheduling\ConflictDetectionService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('understaffed shift produces understaffed conflict', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(Database\Factories\StoreFactory::new()->createOne(), Store::class);

    $start = Carbon\Carbon::now()->addDays(14)->startOfDay();
    $end = $start->copy()->addDays(6)->endOfDay();

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'Test understaffed',
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $manager->getKey(),
    ])->save();

    $shift = new ShiftRequirement();
    $shift->forceFill([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => $start->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '18:00',
        'required_employee_count' => 3,
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ])->save();

    $conflicts = \app(ConflictDetectionService::class);
    $conflicts->recompute($schedule);

    $rows = ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->get();
    $types = $rows->pluck('type')->all();

    \expect($types)->toContain('understaffed');
});

\test('overlap conflict is detected when employee is double-booked', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(Database\Factories\StoreFactory::new()->createOne(), Store::class);

    $employee = Typer::assertInstance(Database\Factories\EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

    $start = Carbon\Carbon::now()->addDays(28)->startOfDay();
    $end = $start->copy()->addDays(6)->endOfDay();

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'Test overlap',
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $manager->getKey(),
    ])->save();

    $a = new ShiftRequirement();
    $a->forceFill([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => $start->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '14:00',
        'required_employee_count' => 1,
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ])->save();

    $b = new ShiftRequirement();
    $b->forceFill([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => $start->format('Y-m-d'),
        'start_time' => '13:00',
        'end_time' => '17:00',
        'required_employee_count' => 1,
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ])->save();

    $assignments = \app(AssignmentService::class);
    $assignments->assign($a, $employee, $manager);
    $assignments->assign($b, $employee, $manager);

    $conflicts = \app(ConflictDetectionService::class);
    $conflicts->recompute($schedule);

    $rows = ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->get();
    $types = $rows->pluck('type')->all();

    \expect($types)->toContain('overlapping_shift');
});

\test('outside business hours conflict is detected', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(Database\Factories\StoreFactory::new()->createOne(), Store::class);

    $start = Carbon\Carbon::now()->addDays(42)->startOfDay();
    $end = $start->copy()->addDays(6)->endOfDay();

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'Test outside hours',
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $manager->getKey(),
    ])->save();

    $shift = new ShiftRequirement();
    $shift->forceFill([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => $start->format('Y-m-d'),
        'start_time' => '06:00',
        'end_time' => '10:00',
        'required_employee_count' => 1,
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ])->save();

    $conflicts = \app(ConflictDetectionService::class);
    $conflicts->recompute($schedule);

    $rows = ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->get();
    $types = $rows->pluck('type')->all();

    \expect($types)->toContain('outside_business_hours');
});

\test('max hours detection accepts database time values with seconds', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(Database\Factories\StoreFactory::new()->createOne(), Store::class);
    $employee = Typer::assertInstance(Database\Factories\EmployeeProfileFactory::new()->createOne([
        'max_hours_per_week' => 1,
    ]), EmployeeProfile::class);

    $start = Carbon\Carbon::now()->addDays(56)->startOfDay();
    $end = $start->copy()->addDays(6)->endOfDay();

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'Test max hours seconds',
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $manager->getKey(),
    ])->save();

    $shift = new ShiftRequirement();
    $shift->forceFill([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => $start->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time' => '15:00:00',
        'required_employee_count' => 1,
        'source' => ShiftSourceEnum::Manual->value,
        'created_by' => $manager->getKey(),
    ])->save();

    $assignments = \app(AssignmentService::class);
    $assignments->assign($shift, $employee, $manager);

    $rows = ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->get();
    $types = $rows->pluck('type')->all();

    \expect($types)->toContain('max_hours_exceeded');
});
