<?php

declare(strict_types=1);

use App\Enums\AvailabilitySourceEnum;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\ScheduleFactory;
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
 * @return array{0: User, 1: EmployeeProfile, 2: Store}
 */
function selfServiceEmployeeAndStore(): array
{
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'employee']), User::class);
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne([
        'user_id' => $user->getKey(),
        'name' => 'Self Service Employee',
    ]), EmployeeProfile::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'Assigned Store']), Store::class);

    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return [$user, $employee, $store];
}

function selfServiceSchedule(Store $store, string $name, string $periodStart, string $status = 'published'): Schedule
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

function selfServiceAvailability(EmployeeProfile $employee, User $creator, string $date, string $source = 'employee', int|null $storeId = null): EmployeeAvailability
{
    return Typer::assertInstance(EmployeeAvailability::query()->create([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $storeId,
        'date' => $date,
        'start_time' => $source === 'employee' ? '09:00' : null,
        'end_time' => $source === 'employee' ? '17:00' : null,
        'type' => $source === 'employee' ? 'available' : 'unavailable',
        'note' => $source,
        'source' => $source,
        'created_by' => $creator->getKey(),
    ]), EmployeeAvailability::class);
}

\test('my calendar exposes assigned store published current and future schedules', function (): void {
    [$user,, $store] = \selfServiceEmployeeAndStore();
    $current = \selfServiceSchedule($store, 'Assigned Store - June 2026', '2026-06-01');
    $future = \selfServiceSchedule($store, 'Assigned Store - July 2026', '2026-07-01');
    \selfServiceSchedule($store, 'Assigned Store - May 2026', '2026-05-01');
    // Past draft / past archived schedules on different periods to
    // exercise the status filter without colliding with the unique
    // (store_id, period_start) index.
    \selfServiceSchedule($store, 'Assigned Store - Draft April 2026', '2026-04-01', 'draft');
    \selfServiceSchedule($store, 'Assigned Store - Archived March 2026', '2026-03-01', 'archived');
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    \selfServiceSchedule($foreignStore, 'Foreign Store - June 2026', '2026-06-01');

    $response = $this->be($user, 'users')->get('/my-calendar', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'calendar/Mine');
    $response->assertJsonPath('props.has_profile', true);
    $response->assertJsonCount(1, 'props.stores');
    $response->assertJsonCount(2, 'props.schedules');
    $response->assertJsonPath('props.schedules.0.id', $current->getKey());
    $response->assertJsonPath('props.schedules.1.id', $future->getKey());
});

\test('my calendar falls back from invalid store and schedule query params', function (): void {
    [$user,, $store] = \selfServiceEmployeeAndStore();
    $schedule = \selfServiceSchedule($store, 'Assigned Store - June 2026', '2026-06-01');
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    $response = $this->be($user, 'users')->get(
        '/my-calendar?store_id=' . $foreignStore->getKey() . '&schedule_id=99999',
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonPath('props.selected_store_id', $store->getKey());
    $response->assertJsonPath('props.selected_schedule.id', $schedule->getKey());
});

\test('my calendar handles users without employee profile', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'employee']), User::class);

    $response = $this->be($user, 'users')->get('/my-calendar', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.has_profile', false);
    $response->assertJsonCount(0, 'props.stores');
});

\test('employee can view and create own global availability', function (): void {
    [$user, $employee] = \selfServiceEmployeeAndStore();
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    \selfServiceAvailability($employee, $manager, '2026-06-16', AvailabilitySourceEnum::Manager->value);

    $index = $this->be($user, 'users')->get('/my-availabilities?month=2026-06', $this->inertiaHeaders());
    $index->assertOk();
    $index->assertJsonPath('component', 'my-availabilities/Index');
    $index->assertJsonPath('props.has_profile', true);
    $index->assertJsonPath('props.entries.0.can_edit', false);

    $created = $this->be($user, 'users')->post('/my-availabilities/store', [
        'date' => '2026-06-17',
        'type' => 'available',
        'start_time' => '10:00',
        'end_time' => '16:00',
        'note' => 'Can work',
    ], $this->inertiaHeaders());

    $created->assertRedirect();
    $created->assertSessionHas('availability_modal_success', \__('Availability added.'));
    $this->assertDatabaseHas('employee_availabilities', [
        'employee_profile_id' => $employee->getKey(),
        'store_id' => null,
        'date' => '2026-06-17',
        'source' => AvailabilitySourceEnum::Employee->value,
        'created_by' => $user->getKey(),
    ]);
});

\test('employee can update and delete only own employee-created global availability', function (): void {
    [$user, $employee, $store] = \selfServiceEmployeeAndStore();
    $own = \selfServiceAvailability($employee, $user, '2026-06-17');
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $managerRow = \selfServiceAvailability($employee, $manager, '2026-06-18', AvailabilitySourceEnum::Manager->value);
    $storeSpecific = \selfServiceAvailability($employee, $user, '2026-06-19', AvailabilitySourceEnum::Employee->value, $store->getKey());
    $foreignEmployee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);
    $foreign = \selfServiceAvailability($foreignEmployee, $user, '2026-06-20');

    $updated = $this->be($user, 'users')->post('/my-availabilities/update?id=' . $own->getKey(), [
        'type' => 'backup',
        'start_time' => '11:00',
        'end_time' => '15:00',
        'note' => 'Updated',
    ], $this->inertiaHeaders());

    $updated->assertRedirect();
    $this->assertDatabaseHas('employee_availabilities', [
        'id' => $own->getKey(),
        'type' => 'backup',
        'start_time' => '11:00',
        'end_time' => '15:00',
        'note' => 'Updated',
    ]);

    $this->be($user, 'users')->post('/my-availabilities/update?id=' . $managerRow->getKey(), [
        'type' => 'available',
        'start_time' => '10:00',
        'end_time' => '12:00',
    ], $this->inertiaHeaders())->assertForbidden();
    $this->be($user, 'users')->post('/my-availabilities/update?id=' . $storeSpecific->getKey(), [
        'type' => 'available',
        'start_time' => '10:00',
        'end_time' => '12:00',
    ], $this->inertiaHeaders())->assertForbidden();
    $this->be($user, 'users')->post('/my-availabilities/destroy?id=' . $foreign->getKey(), [], $this->inertiaHeaders())->assertForbidden();

    $deleted = $this->be($user, 'users')->post('/my-availabilities/destroy?id=' . $own->getKey(), [], $this->inertiaHeaders());

    $deleted->assertRedirect();
    $this->assertDatabaseMissing('employee_availabilities', ['id' => $own->getKey()]);
    $this->assertDatabaseHas('employee_availabilities', ['id' => $managerRow->getKey()]);
    $this->assertDatabaseHas('employee_availabilities', ['id' => $storeSpecific->getKey()]);
});

\test('employee availability rejects duplicate employee-created global date', function (): void {
    [$user, $employee] = \selfServiceEmployeeAndStore();
    \selfServiceAvailability($employee, $user, '2026-06-17');

    $response = $this->be($user, 'users')->post('/my-availabilities/store', [
        'date' => '2026-06-17',
        'type' => 'available',
        'start_time' => '10:00',
        'end_time' => '16:00',
    ], $this->inertiaHeaders());

    $response->assertRedirect();
    $response->assertSessionHas('availability_modal_error', \__('You already have availability for this date.'));
});

\test('employee cannot use manager availability endpoints to bypass self-service rules', function (): void {
    [$user, $employee] = \selfServiceEmployeeAndStore();
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $managerRow = \selfServiceAvailability($employee, $manager, '2026-06-18', AvailabilitySourceEnum::Manager->value);

    $this->be($user, 'users')->get('/availability?month=2026-06', $this->inertiaHeaders())->assertForbidden();
    $this->be($user, 'users')->post('/availability/store', [
        'employee_profile_id' => $employee->getKey(),
        'date' => '2026-06-19',
        'type' => 'available',
        'start_time' => '10:00',
        'end_time' => '16:00',
    ], $this->inertiaHeaders())->assertForbidden();
    $this->be($user, 'users')->post('/availability/update?id=' . $managerRow->getKey(), [
        'type' => 'available',
        'start_time' => '10:00',
        'end_time' => '16:00',
    ], $this->inertiaHeaders())->assertForbidden();
    $this->be($user, 'users')->post('/availability/destroy?id=' . $managerRow->getKey(), [], $this->inertiaHeaders())->assertForbidden();

    $this->assertDatabaseHas('employee_availabilities', ['id' => $managerRow->getKey()]);
});

\test('my availabilities handles users without employee profile', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'employee']), User::class);

    $response = $this->be($user, 'users')->get('/my-availabilities?month=2026-06', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.has_profile', false);
    $response->assertJsonCount(0, 'props.entries');
});
