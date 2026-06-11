<?php

declare(strict_types=1);

use App\Enums\ShiftSourceEnum;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Services\Scheduling\ConflictDetectionService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('understaffed shift produces understaffed conflict', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);
    $store = App\Support\Db::hydrateOne(Store::query()->first(), Store::class);
    if (!$store instanceof Store) {
        $this->markTestSkipped('No seeded store');
    }

    $start = Carbon\Carbon::now()->addDays(14)->startOfDay();
    $end = $start->copy()->addDays(6)->endOfDay();

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'Test understaffed',
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $admin->getKey(),
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
        'created_by' => $admin->getKey(),
    ])->save();

    $conflicts = \app(ConflictDetectionService::class);
    $conflicts->recompute($schedule);

    $rows = App\Models\ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->get();
    $types = $rows->pluck('type')->all();

    \expect($types)->toContain('understaffed');
});

\test('overlap conflict is detected when employee is double-booked', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);
    $store = App\Support\Db::hydrateOne(Store::query()->first(), Store::class);
    if (!$store instanceof Store) {
        $this->markTestSkipped('No seeded store');
    }

    $employee = App\Support\Db::hydrateOne(EmployeeProfile::query()->first(), EmployeeProfile::class);
    if (!$employee instanceof EmployeeProfile) {
        $this->markTestSkipped('No employee');
    }

    $start = Carbon\Carbon::now()->addDays(28)->startOfDay();
    $end = $start->copy()->addDays(6)->endOfDay();

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'Test overlap',
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $admin->getKey(),
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
        'created_by' => $admin->getKey(),
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
        'created_by' => $admin->getKey(),
    ])->save();

    $assignments = \app(AssignmentService::class);
    $assignments->assign($a->getKey(), [$employee->getKey()], $admin->getKey());
    $assignments->assign($b->getKey(), [$employee->getKey()], $admin->getKey());

    $conflicts = \app(ConflictDetectionService::class);
    $conflicts->recompute($schedule);

    $rows = App\Models\ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->get();
    $types = $rows->pluck('type')->all();

    \expect($types)->toContain('overlap');
});

\test('outside business hours conflict is detected', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);
    $store = App\Support\Db::hydrateOne(Store::query()->first(), Store::class);
    if (!$store instanceof Store) {
        $this->markTestSkipped('No seeded store');
    }

    $start = Carbon\Carbon::now()->addDays(42)->startOfDay();
    $end = $start->copy()->addDays(6)->endOfDay();

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'Test outside hours',
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $admin->getKey(),
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
        'created_by' => $admin->getKey(),
    ])->save();

    $conflicts = \app(ConflictDetectionService::class);
    $conflicts->recompute($schedule);

    $rows = App\Models\ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->get();
    $types = $rows->pluck('type')->all();

    \expect($types)->toContain('outside_business_hours');
});
