<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Attach a store to a manager via the store_manager_store pivot.
 */
function attachManagedStore(User $manager, Store $store): void
{
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);
}

/**
 * Attach an employee to a store via the employee_store pivot.
 */
function attachEmployeeToStore(EmployeeProfile $employee, Store $store): void
{
    DB::table('employee_store')->insert([
        'employee_profile_id' => $employee->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);
}

\test('managedEmployeesQuery returns every employee that belongs to any of the manager\'s stores', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);

    $storeA = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'A']), Store::class);
    $storeB = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'B']), Store::class);
    $storeUnmanaged = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'Unmanaged']), Store::class);

    \attachManagedStore($manager, $storeA);
    \attachManagedStore($manager, $storeB);

    $onlyA = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['name' => 'Alice']), EmployeeProfile::class);
    $onlyB = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['name' => 'Bob']), EmployeeProfile::class);
    $both = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['name' => 'Cleo']), EmployeeProfile::class);
    $unmanaged = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['name' => 'Dan']), EmployeeProfile::class);

    \attachEmployeeToStore($onlyA, $storeA);
    \attachEmployeeToStore($onlyB, $storeB);
    \attachEmployeeToStore($both, $storeA);
    \attachEmployeeToStore($both, $storeB);
    \attachEmployeeToStore($unmanaged, $storeUnmanaged);

    $ids = Authorization::managedEmployeesQuery($manager)->pluck('id')->all();

    \expect(\collect($ids)->sort()->values()->all())
        ->toBe(\collect([$onlyA->getKey(), $onlyB->getKey(), $both->getKey()])->sort()->values()->all());
});

\test('managedEmployeesQuery does not regress when the manager\'s store pivot is loaded in opposite order', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);

    $first = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'First']), Store::class);
    $second = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'Second']), Store::class);
    $third = Typer::assertInstance(StoreFactory::new()->createOne(['name' => 'Third']), Store::class);

    \attachManagedStore($manager, $first);
    \attachManagedStore($manager, $second);
    \attachManagedStore($manager, $third);

    // Employee is attached to the second and third stores only — would be
    // dropped by the previous `where('stores.id', $array)` bug because the
    // inner EXISTS collapsed to `stores.id = $first`.
    $victim = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['name' => 'Eve']), EmployeeProfile::class);
    \attachEmployeeToStore($victim, $second);
    \attachEmployeeToStore($victim, $third);

    \expect(Authorization::managedEmployeesQuery($manager)->pluck('id')->all())
        ->toContain($victim->getKey());
});

\test('managedEmployeesQuery falls back to user_id filter for non-manager users', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::Employee->value,
    ]), User::class);

    $owned = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(['user_id' => $user->getKey()]), EmployeeProfile::class);
    $other = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);

    $ids = Authorization::managedEmployeesQuery($user)->pluck('id')->all();

    \expect($ids)->toBe([$owned->getKey()])
        ->and($ids)->not->toContain($other->getKey());
});
