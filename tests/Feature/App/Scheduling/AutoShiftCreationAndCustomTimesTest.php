<?php

declare(strict_types=1);

use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\StoreBusinessHour;
use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

\test('shifts are automatically created based on store business hours upon schedule creation', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    // Set up business hours:
    // Monday (1) -> Open 08:00 - 16:00
    // Tuesday (2) -> Closed
    // Wednesday (3) -> Open 10:00 - 20:00
    StoreBusinessHour::query()->updateOrCreate(
        ['store_id' => $store->getKey(), 'day_of_week' => 1],
        ['opens_at' => '08:00', 'closes_at' => '16:00', 'is_closed' => false],
    );
    StoreBusinessHour::query()->updateOrCreate(
        ['store_id' => $store->getKey(), 'day_of_week' => 2],
        ['opens_at' => null, 'closes_at' => null, 'is_closed' => true],
    );
    StoreBusinessHour::query()->updateOrCreate(
        ['store_id' => $store->getKey(), 'day_of_week' => 3],
        ['opens_at' => '10:00', 'closes_at' => '20:00', 'is_closed' => false],
    );

    // Create schedule for June 2026 (starts on Monday June 1st)
    $response = $this->be($manager, 'users')->post('/schedules/store', [
        'store_id' => $store->getKey(),
        'month' => 6,
        'year' => 2026,
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $schedule = Schedule::query()->where('store_id', $store->getKey())->firstOrFail();

    // Monday June 1st should have a shift
    $this->assertDatabaseHas('shift_requirements', [
        'schedule_id' => $schedule->getKey(),
        'date' => '2026-06-01 00:00:00',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    // Tuesday June 2nd should NOT have a shift (closed)
    $this->assertDatabaseMissing('shift_requirements', [
        'schedule_id' => $schedule->getKey(),
        'date' => '2026-06-02 00:00:00',
    ]);

    // Wednesday June 3rd should have a shift
    $this->assertDatabaseHas('shift_requirements', [
        'schedule_id' => $schedule->getKey(),
        'date' => '2026-06-03 00:00:00',
        'start_time' => '10:00',
        'end_time' => '20:00',
    ]);
});

\test('assigning employee validates custom times and saves them on assignment', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

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

    $schedule = new Schedule();
    $schedule->forceFill([
        'store_id' => $store->getKey(),
        'name' => 'June Schedule',
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => 'draft',
        'created_by' => $manager->getKey(),
    ])->save();

    $shift = new ShiftRequirement();
    $shift->forceFill([
        'schedule_id' => $schedule->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-03',
        'start_time' => '10:00',
        'end_time' => '20:00',
        'source' => 'manual',
        'created_by' => $manager->getKey(),
    ])->save();

    // 1. Assign within range (10:00 - 15:00) should succeed
    $response = $this->be($manager, 'users')->post('/shift-assignments/store', [
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '10:00',
        'end_time' => '15:00',
    ], $this->inertiaHeaders());

    $response->assertRedirect();
    $this->assertDatabaseHas('shift_assignments', [
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);

    // 2. Assign overlapping different time (15:00 - 20:00) should succeed
    $response = $this->be($manager, 'users')->post('/shift-assignments/store', [
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '15:00',
        'end_time' => '20:00',
    ], $this->inertiaHeaders());

    $response->assertRedirect();
    $this->assertDatabaseHas('shift_assignments', [
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '15:00',
        'end_time' => '20:00',
    ]);

    // 3. Assign outside start time (09:00 - 12:00) should fail validation (outside shift business hours)
    $response = $this->be($manager, 'users')->post('/shift-assignments/store', [
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '09:00',
        'end_time' => '12:00',
    ], $this->inertiaHeaders());

    $this->assertNotEmpty($response->json('props.errors.start_time'));

    // 4. Assign outside end time (18:00 - 21:00) should fail validation (outside shift business hours)
    $response = $this->be($manager, 'users')->post('/shift-assignments/store', [
        'shift_requirement_id' => $shift->getKey(),
        'employee_profile_id' => $employee->getKey(),
        'start_time' => '18:00',
        'end_time' => '21:00',
    ], $this->inertiaHeaders());

    $this->assertNotEmpty($response->json('props.errors.start_time'));
});
