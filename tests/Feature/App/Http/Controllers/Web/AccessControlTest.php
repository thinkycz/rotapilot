<?php

declare(strict_types=1);

use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\StoreBusinessHour;
use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\ScheduleFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

\test('store manager can view stores index', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);

    $response = $this->be($user, 'users')->get('/stores/index', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Index');
});

\test('store manager not assigned cannot view foreign store', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    $response = $this->be($manager, 'users')->get('/stores/show?id=' . $store->getKey());

    $response->assertForbidden();
});

\test('store manager assigned to a store can view it', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->get('/stores/show?id=' . $store->getKey(), $this->inertiaHeaders());

    $response->assertOk();
});

\test('store manager can view schedules index', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);

    $response = $this->be($user, 'users')->get('/schedules/index', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'schedules/Index');
});

\test('store manager can view my calendar without an employee profile', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);

    $response = $this->be($user, 'users')->get('/my-calendar', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'calendar/Mine');
});

\test('store manager can view stores create form', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);

    $response = $this->be($user, 'users')->get('/stores/create', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Edit');
});

\test('store manager can successfully create a store', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);

    $response = $this->be($user, 'users')->post('/stores/store', [
        'name' => 'Store Created by Manager Test',
        'timezone' => 'UTC',
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('stores', [
        'name' => 'Store Created by Manager Test',
    ]);

    $store = Store::query()->where('name', 'Store Created by Manager Test')->first();
    $this->assertNotNull($store);

    $this->assertDatabaseHas('store_manager_store', [
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
    ]);
});

\test('store manager can delete an assigned store', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/stores/destroy?id=' . $store->getKey(), [], $this->inertiaHeaders());

    $response->assertRedirect('/stores/index');

    $this->assertDatabaseMissing('stores', [
        'id' => $store->getKey(),
    ]);
});

\test('store manager cannot access employee they do not manage', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

    $response = $this->be($manager, 'users')->get('/employees/show?id=' . $employee->getKey(), $this->inertiaHeaders());

    $response->assertForbidden();
});

\test('store manager cannot delete employee they do not manage', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

    $response = $this->be($manager, 'users')->post('/employees/destroy?id=' . $employee->getKey(), [], $this->inertiaHeaders());

    $response->assertForbidden();

    $this->assertDatabaseHas('employee_profiles', [
        'id' => $employee->getKey(),
    ]);
});

\test('store manager can create employee only with their managed stores', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    // Assign store to manager
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    // Try creating employee without stores - should fail validation
    $response = $this->be($manager, 'users')->post('/employees/store', [
        'name' => 'John Doe',
        'store_ids' => [],
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('props.errors.store_ids.0', 'The store ids field is required.');

    // Try creating employee with a store they don't manage - should fail validation
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    $response = $this->be($manager, 'users')->post('/employees/store', [
        'name' => 'John Doe',
        'store_ids' => [$foreignStore->getKey()],
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('props.errors.store_ids.0', 'You must select at least one store that you manage.');

    // Create employee with managed store - should succeed
    $response = $this->be($manager, 'users')->post('/employees/store', [
        'name' => 'John Doe',
        'store_ids' => [$store->getKey()],
    ], $this->inertiaHeaders());

    $response->assertRedirect(); // redirects to /employees/show?id=...

    $employee = EmployeeProfile::query()->where('name', 'John Doe')->first();
    $this->assertNotNull($employee);

    $this->assertDatabaseHas('employee_store', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
    ]);
});

\test('store manager can delete an assigned employee', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);
    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/employees/destroy?id=' . $employee->getKey(), [], $this->inertiaHeaders());

    $response->assertRedirect('/employees/index');

    $this->assertDatabaseMissing('employee_profiles', [
        'id' => $employee->getKey(),
    ]);
});

\test('store manager can update employee but cannot assign store they do not manage', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    // Assign store to manager
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    // Create employee assigned to managed store
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);
    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    // Assign employee to a foreign store as well
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $foreignStore->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    // Manager updates employee's name and store list (omitting the store they manage)
    // Should fail validation because store_ids would only have stores they don't manage
    $response = $this->be($manager, 'users')->post('/employees/update?id=' . $employee->getKey(), [
        'name' => 'John Updated',
        'store_ids' => [$foreignStore->getKey()],
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('props.errors.store_ids.0', 'You must select at least one store that you manage.');

    // Manager updates with managed store. Foreign store should NOT be detached.
    $response = $this->be($manager, 'users')->post('/employees/update?id=' . $employee->getKey(), [
        'name' => 'John Updated',
        'store_ids' => [$store->getKey()],
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('employee_profiles', [
        'id' => $employee->getKey(),
        'name' => 'John Updated',
    ]);

    // Managed store is still attached
    $this->assertDatabaseHas('employee_store', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
    ]);

    // Foreign store is still attached (untouched by manager)
    $this->assertDatabaseHas('employee_store', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $foreignStore->getKey(),
    ]);
});

\test('store manager can successfully create a schedule', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/schedules/store', [
        'store_id' => $store->getKey(),
        'month' => 6,
        'year' => 2026,
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('schedules', [
        'name' => $store->getName() . ' - June 2026',
        'store_id' => $store->getKey(),
        'period_start' => '2026-06-01 00:00:00',
        'period_end' => '2026-06-30 00:00:00',
    ]);
});

\test('store manager can successfully update a schedule', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'name' => 'Old Schedule Name',
    ]), Schedule::class);

    $response = $this->be($manager, 'users')->post('/schedules/update?id=' . $schedule->getKey(), [
        'month' => 2,
        'year' => 2028,
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->getKey(),
        'name' => $store->getName() . ' - February 2028',
        'period_start' => '2028-02-01 00:00:00',
        'period_end' => '2028-02-29 00:00:00',
    ]);
});

\test('store manager cannot update a schedule for a store they do not manage', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $foreignStore->getKey(),
        'name' => 'Foreign Schedule',
    ]), Schedule::class);

    $response = $this->be($manager, 'users')->post('/schedules/update?id=' . $schedule->getKey(), [
        'month' => 5,
        'year' => 2027,
    ], $this->inertiaHeaders());

    $response->assertForbidden();

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->getKey(),
        'name' => 'Foreign Schedule',
    ]);
});

\test('store manager can delete an assigned schedule', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
    ]), Schedule::class);

    $response = $this->be($manager, 'users')->post('/schedules/destroy?id=' . $schedule->getKey(), [], $this->inertiaHeaders());

    $response->assertRedirect('/schedules/index');

    $this->assertDatabaseMissing('schedules', [
        'id' => $schedule->getKey(),
    ]);
});

\test('store manager can successfully create a shift requirement without selected employees', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
    ]), Schedule::class);

    $response = $this->be($manager, 'users')->post('/shift-requirements/store?schedule_id=' . $schedule->getKey(), [
        'date' => '2026-06-16',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'role_label' => 'Barista',
        'note' => 'Test shift note',
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('shift_requirements', [
        'schedule_id' => $schedule->getKey(),
        'date' => '2026-06-16 00:00:00',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    $requirement = ShiftRequirement::query()
        ->where('schedule_id', $schedule->getKey())
        ->where('date', '2026-06-16 00:00:00')
        ->first();
    $this->assertNotNull($requirement);

    $this->assertDatabaseMissing('shift_assignments', [
        'shift_requirement_id' => $requirement->getKey(),
    ]);
});

\test('store manager can create a shift requirement with selected employees', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
    ]), Schedule::class);
    $employeeOne = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);
    $employeeTwo = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

    foreach ([$employeeOne, $employeeTwo] as $employee) {
        DB::table('employee_store')->insert([
            'employee_profile_id' => $employee->getKey(),
            'store_id' => $store->getKey(),
            'created_at' => \now(),
            'updated_at' => \now(),
        ]);
    }

    $response = $this->be($manager, 'users')->post('/shift-requirements/store?schedule_id=' . $schedule->getKey(), [
        'date' => '2026-06-17',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'role_label' => 'Barista',
        'note' => 'Assigned during creation',
        'employee_profile_ids' => [$employeeOne->getKey(), $employeeTwo->getKey()],
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $requirement = ShiftRequirement::query()
        ->where('schedule_id', $schedule->getKey())
        ->where('date', '2026-06-17 00:00:00')
        ->first();
    $this->assertNotNull($requirement);

    foreach ([$employeeOne, $employeeTwo] as $employee) {
        $this->assertDatabaseHas('shift_assignments', [
            'shift_requirement_id' => $requirement->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'assigned_by' => $manager->getKey(),
        ]);
    }

    $showResponse = $this->be($manager, 'users')->get('/schedules/show?id=' . $schedule->getKey(), $this->inertiaHeaders());

    $showResponse->assertOk();
    $showResponse->assertJsonPath('component', 'schedules/Show');
    $days = $showResponse->json('props.days');
    $names = \array_column($days['2026-06-17']['shifts'][0]['assignments'], 'employee_name');

    \expect($names)->toContain($employeeOne->getName(), $employeeTwo->getName());
});

\test('store manager cannot assign a foreign employee while creating a shift requirement', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
    ]), Schedule::class);
    $foreignEmployee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

    $response = $this->be($manager, 'users')->post('/shift-requirements/store?schedule_id=' . $schedule->getKey(), [
        'date' => '2026-06-18',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'role_label' => null,
        'note' => null,
        'employee_profile_ids' => [$foreignEmployee->getKey()],
    ], $this->inertiaHeaders());

    $response->assertForbidden();

    $this->assertDatabaseMissing('shift_requirements', [
        'schedule_id' => $schedule->getKey(),
        'date' => '2026-06-18 00:00:00',
    ]);
});

\test('store manager gets validation errors for invalid shift creation employee ids', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $schedule = Typer::assertInstance(ScheduleFactory::new()->createOne([
        'store_id' => $store->getKey(),
    ]), Schedule::class);

    $response = $this->be($manager, 'users')->post('/shift-requirements/store?schedule_id=' . $schedule->getKey(), [
        'date' => '2026-06-19',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'role_label' => null,
        'note' => null,
        'employee_profile_ids' => [999999],
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $this->assertNotEmpty($response->json('props.errors'));

    $this->assertDatabaseMissing('shift_requirements', [
        'schedule_id' => $schedule->getKey(),
        'date' => '2026-06-19 00:00:00',
    ]);
});

\test('store manager can successfully create and update employee availability', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);
    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    // Create availability
    $response = $this->be($manager, 'users')->post('/availability/store', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2026-06-17',
        'type' => 'available',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'note' => 'Manager created availability',
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('employee_availabilities', [
        'employee_profile_id' => $employee->getKey(),
        'date' => '2026-06-17',
        'type' => 'available',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    $availability = EmployeeAvailability::query()->where('employee_profile_id', $employee->getKey())->first();
    $this->assertNotNull($availability);

    // Update availability
    $response = $this->be($manager, 'users')->post('/availability/update?id=' . $availability->getKey(), [
        'type' => 'unavailable',
        'start_time' => null,
        'end_time' => null,
        'note' => 'Updated availability',
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('employee_availabilities', [
        'id' => $availability->getKey(),
        'type' => 'unavailable',
        'start_time' => null,
        'end_time' => null,
    ]);
});

\test('store manager can successfully create availability with store_id 0', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);
    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    // Create availability with store_id => 0
    $response = $this->be($manager, 'users')->post('/availability/store', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => 0,
        'date' => '2026-06-18',
        'type' => 'available',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'note' => 'Manager created availability with store 0',
    ], $this->inertiaHeaders());

    $response->assertRedirect();

    $this->assertDatabaseHas('employee_availabilities', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => null, // Should be saved as null
        'date' => '2026-06-18',
        'type' => 'available',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
});

\test('store manager can save business hours and is redirected to store detail', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/stores/business-hours/update?id=' . $store->getKey(), [
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
    ], $this->inertiaHeaders());

    $response->assertRedirect('/stores/show?id=' . $store->getKey());
    $response->assertSessionHas('success', \__('Business hours updated.'));

    $this->assertDatabaseHas('store_business_hours', [
        'store_id' => $store->getKey(),
        'day_of_week' => 1,
        'opens_at' => '08:00',
        'closes_at' => '16:00',
        'is_closed' => false,
    ]);
    $this->assertDatabaseHas('store_business_hours', [
        'store_id' => $store->getKey(),
        'day_of_week' => 7,
        'opens_at' => null,
        'closes_at' => null,
        'is_closed' => true,
    ]);
});

\test('open business hours require opening and closing times', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this
        ->be($manager, 'users')
        ->from('/stores/business-hours?id=' . $store->getKey())
        ->post('/stores/business-hours/update?id=' . $store->getKey(), [
            'hours' => [
                [
                    'day_of_week' => 1,
                    'opens_at' => null,
                    'closes_at' => null,
                    'is_closed' => false,
                ],
            ],
        ], $this->inertiaHeaders());

    $response->assertRedirect('/stores/business-hours?id=' . $store->getKey());
    $response->assertSessionHas('error', \__('Open days need opening and closing times.'));

    $this->assertDatabaseMissing('store_business_hours', [
        'store_id' => $store->getKey(),
        'day_of_week' => 1,
    ]);
});

\test('store detail returns monday first business hours with missing days filled', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    StoreBusinessHour::query()->create([
        'store_id' => $store->getKey(),
        'day_of_week' => 7,
        'opens_at' => null,
        'closes_at' => null,
        'is_closed' => true,
    ]);
    StoreBusinessHour::query()->create([
        'store_id' => $store->getKey(),
        'day_of_week' => 1,
        'opens_at' => '09:00',
        'closes_at' => '17:00',
        'is_closed' => false,
    ]);

    $response = $this->be($manager, 'users')->get('/stores/show?id=' . $store->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Show');

    $hours = $response->json('props.business_hours');
    \expect($hours)->toHaveCount(7);
    \expect(\array_column($hours, 'day_of_week'))->toBe([1, 2, 3, 4, 5, 6, 7]);
    \expect($hours[0]['opens_at'])->toBe('09:00');
    \expect($hours[1]['opens_at'])->toBeNull();
    \expect($hours[6]['is_closed'])->toBeTrue();
});
