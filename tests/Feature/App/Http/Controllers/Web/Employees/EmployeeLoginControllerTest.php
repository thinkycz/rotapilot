<?php

declare(strict_types=1);

use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @return array{0: User, 1: EmployeeProfile}
 */
function employeeLoginManagerAndEmployee(): array
{
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $store = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne([
        'email' => 'employee@example.com',
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

    return [$manager, $employee];
}

function employeeLoginUser(EmployeeProfile $employee, string $email = 'login@example.com', string $password = 'password'): User
{
    $login = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => $email,
        'password' => $password,
        'role' => 'employee',
        'locale' => 'en',
        'is_active' => true,
    ]), User::class);

    $employee->forceFill(['user_id' => $login->getKey()])->save();

    return $login;
}

\test('store manager can create a linked employee login with manual password', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();

    $response = $this->be($manager, 'users')->post('/employees/login/store?id=' . $employee->getKey(), [
        'email' => 'new-login@example.com',
        'locale' => 'en',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    $employee->refresh();
    $login = User::query()->where('email', 'new-login@example.com')->first();

    static::assertInstanceOf(User::class, $login);
    static::assertSame($login->getKey(), $employee->getUserId());
    static::assertSame('employee', $login->getRole()->value);
    static::assertTrue($login->getIsActive());
    static::assertSame('en', $login->getLocale());
    static::assertTrue(Resolver::resolveHasher()->check('password', $login->getAuthPassword()));
});

\test('store manager cannot manage login for foreign employee', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $employee = Typer::assertInstance(EmployeeProfileFactory::new()->createOne(), EmployeeProfile::class);
    $login = \employeeLoginUser($employee);

    $create = $this->be($manager, 'users')->post('/employees/login/store?id=' . $employee->getKey(), [
        'email' => 'new-login@example.com',
        'locale' => 'en',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $this->inertiaHeaders());
    $update = $this->be($manager, 'users')->post('/employees/login/update?id=' . $employee->getKey(), [
        'email' => 'updated@example.com',
        'locale' => 'cs',
    ], $this->inertiaHeaders());
    $destroy = $this->be($manager, 'users')->post('/employees/login/destroy?id=' . $employee->getKey(), [], $this->inertiaHeaders());

    $create->assertForbidden();
    $update->assertForbidden();
    $destroy->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $login->getKey()]);
});

\test('creating login rejects duplicate email and existing linked login', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();
    Typer::assertInstance(UserFactory::new()->createOne(['email' => 'taken@example.com']), User::class);

    $duplicate = $this->be($manager, 'users')->post('/employees/login/store?id=' . $employee->getKey(), [
        'email' => 'taken@example.com',
        'locale' => 'en',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $this->inertiaHeaders());

    $duplicate->assertStatus(422);
    $duplicate->assertJsonPath('props.errors.email.0', 'The email has already been taken.');

    \employeeLoginUser($employee);

    $existing = $this->be($manager, 'users')->post('/employees/login/store?id=' . $employee->getKey(), [
        'email' => 'another@example.com',
        'locale' => 'en',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $this->inertiaHeaders());

    $existing->assertStatus(422);
    $existing->assertJsonPath('props.errors.login.0', 'Employee already has a login account.');
});

\test('store manager can update linked login email and locale', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();
    $login = \employeeLoginUser($employee);

    $response = $this->be($manager, 'users')->post('/employees/login/update?id=' . $employee->getKey(), [
        'email' => 'updated-login@example.com',
        'locale' => 'sk',
    ], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    $login->refresh();
    static::assertSame('updated-login@example.com', $login->getEmail());
    static::assertSame('sk', $login->getLocale());
});

\test('store manager can manually reset password and revoke login tokens', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();
    $login = \employeeLoginUser($employee);
    DB::table('database_tokens')->insert([
        'hash' => \str_repeat('a', 64),
        'user_id' => $login->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/employees/login/password?id=' . $employee->getKey(), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    $login->refresh();
    static::assertTrue(Resolver::resolveHasher()->check('new-password', $login->getAuthPassword()));
    $this->assertDatabaseMissing('database_tokens', ['user_id' => $login->getKey()]);
});

\test('manual password reset rejects mismatched confirmation', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();
    \employeeLoginUser($employee);

    $response = $this->be($manager, 'users')->post('/employees/login/password?id=' . $employee->getKey(), [
        'password' => 'new-password',
        'password_confirmation' => 'different-password',
    ], $this->inertiaHeaders());

    $response->assertStatus(422);
    $response->assertJsonPath('props.errors.password_confirmation.0', \__('auth.password_mismatch'));
});

\test('store manager can generate random password and revoke login tokens', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();
    $login = \employeeLoginUser($employee, password: 'old-password');
    DB::table('database_tokens')->insert([
        'hash' => \str_repeat('b', 64),
        'user_id' => $login->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->post('/employees/login/generate-password?id=' . $employee->getKey(), [], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());
    $response->assertSessionHas('employee_login_generated_password');

    $password = $response->baseResponse->getSession()?->get('employee_login_generated_password');
    static::assertIsString($password);

    $login->refresh();
    static::assertTrue(Resolver::resolveHasher()->check($password, $login->getAuthPassword()));
    static::assertFalse(Resolver::resolveHasher()->check('old-password', $login->getAuthPassword()));
    $this->assertDatabaseMissing('database_tokens', ['user_id' => $login->getKey()]);
});

\test('store manager can create login with generated random password', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();

    $response = $this->be($manager, 'users')->post('/employees/login/store?id=' . $employee->getKey(), [
        'email' => 'generated@example.com',
        'locale' => 'cs',
        'generate_random' => true,
    ], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());
    $response->assertSessionHas('employee_login_generated_password');

    $password = $response->baseResponse->getSession()?->get('employee_login_generated_password');
    static::assertIsString($password);

    $login = Typer::assertInstance(User::query()->where('email', 'generated@example.com')->first(), User::class);
    static::assertTrue(Resolver::resolveHasher()->check($password, $login->getAuthPassword()));
    static::assertSame('cs', $login->getLocale());
});

\test('store manager can delete login without deleting employee profile', function (): void {
    [$manager, $employee] = \employeeLoginManagerAndEmployee();
    $login = \employeeLoginUser($employee);

    $response = $this->be($manager, 'users')->post('/employees/login/destroy?id=' . $employee->getKey(), [], $this->inertiaHeaders());

    $response->assertRedirect('/employees/show?id=' . $employee->getKey());

    $this->assertDatabaseMissing('users', ['id' => $login->getKey()]);
    $this->assertDatabaseHas('employee_profiles', [
        'id' => $employee->getKey(),
        'user_id' => null,
    ]);
});
