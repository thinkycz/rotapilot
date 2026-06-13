<?php

declare(strict_types=1);

use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @return array{0: User, 1: Store}
 */
function employeeSetupManagerAndStore(): array
{
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    return [$manager, $store];
}

\test('store manager can create an employee with an hourly rate', function (): void {
    [$manager, $store] = \employeeSetupManagerAndStore();

    $response = $this->be($manager, 'users')->post('/employees/store', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+420123456789',
        'role_label' => 'Cashier',
        'max_hours_per_week' => 40,
        'hourly_rate' => 150,
        'is_active' => true,
        'store_ids' => [$store->getKey()],
    ], $this->inertiaHeaders());

    $employee = Typer::assertInstance(EmployeeProfile::query()->where('email', 'john@example.com')->first(), EmployeeProfile::class);

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    static::assertSame(150, $employee->getHourlyRate());
});

\test('store manager can create an employee without an hourly rate', function (): void {
    [$manager, $store] = \employeeSetupManagerAndStore();

    $response = $this->be($manager, 'users')->post('/employees/store', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+420123456789',
        'role_label' => 'Cashier',
        'max_hours_per_week' => 40,
        'hourly_rate' => null,
        'is_active' => true,
        'store_ids' => [$store->getKey()],
    ], $this->inertiaHeaders());

    $employee = Typer::assertInstance(EmployeeProfile::query()->where('email', 'john@example.com')->first(), EmployeeProfile::class);

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    static::assertNull($employee->getHourlyRate());
});

\test('employee creation fails with invalid hourly rate', function (): void {
    [$manager, $store] = \employeeSetupManagerAndStore();

    $response = $this->be($manager, 'users')->post('/employees/store', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+420123456789',
        'role_label' => 'Cashier',
        'max_hours_per_week' => 40,
        'hourly_rate' => -10,
        'is_active' => true,
        'store_ids' => [$store->getKey()],
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('props.errors.hourly_rate.0', 'The hourly rate field must be at least 0.');
});

\test('store manager can update an employee hourly rate', function (): void {
    [$manager, $store] = \employeeSetupManagerAndStore();
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['hourly_rate' => 150]), EmployeeProfile::class);

    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/employees/update?id=' . $employee->getKey(), [
        'name' => 'John Doe Updated',
        'email' => 'john@example.com',
        'phone' => '+420123456789',
        'role_label' => 'Cashier',
        'max_hours_per_week' => 40,
        'hourly_rate' => 200,
        'is_active' => true,
        'store_ids' => [$store->getKey()],
    ], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    $employee->refresh();
    static::assertSame(200, $employee->getHourlyRate());
});

\test('store manager can clear an employee hourly rate', function (): void {
    [$manager, $store] = \employeeSetupManagerAndStore();
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['hourly_rate' => 150]), EmployeeProfile::class);

    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/employees/update?id=' . $employee->getKey(), [
        'name' => 'John Doe Updated',
        'email' => 'john@example.com',
        'phone' => '+420123456789',
        'role_label' => 'Cashier',
        'max_hours_per_week' => 40,
        'hourly_rate' => null,
        'is_active' => true,
        'store_ids' => [$store->getKey()],
    ], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    $employee->refresh();
    static::assertNull($employee->getHourlyRate());
});
