<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class EmployeeLoginController
{
    use ValidatesWebRequests;

    /**
     * Create a linked employee login.
     */
    public function store(Request $request): SymfonyResponse
    {
        $employee = $this->employee($request);
        if ($employee->getUserId() !== null) {
            Thrower::default()->message('login', Typer::assertString(\__('Employee already has a login account.')))->throw();
        }

        $authValidity = AuthValidity::inject();
        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->unique('users', 'email')->required()->toArray(),
            'locale' => $authValidity->locale()->required()->toArray(),
            'generate_random' => 'nullable|boolean',
            'password' => $authValidity->password()->nullable()->toArray(),
            'password_confirmation' => $authValidity->password()->nullable()->toArray(),
        ]);

        $generateRandom = $request->boolean('generate_random');
        $password = $generateRandom ? Str::password(16) : $validated->assertNullableString('password');
        if ($password === null || $password === '') {
            Thrower::default()->message('password', Typer::assertString(\__('The password field is required.')))->throw();
        }

        if (!$generateRandom && $password !== $validated->assertNullableString('password_confirmation')) {
            Thrower::default()->message('password_confirmation', Typer::assertString(\__('auth.password_mismatch')))->throw();
        }

        DB::transaction(static function () use ($employee, $validated, $password): void {
            $user = User::create([
                'email' => $validated->assertString('email'),
                'locale' => $validated->assertString('locale'),
                'password' => $password,
                'role' => UserRoleEnum::Employee->value,
                'is_active' => true,
            ]);

            $employee->forceFill(['user_id' => $user->getKey()])->save();
        });

        if ($generateRandom) {
            $request->session()->flash('employee_login_generated_password', $password);
            $request->session()->flash('success', \__('Employee login created with a generated password.'));
        } else {
            $request->session()->flash('success', \__('Employee login created.'));
        }

        return \redirect('/employees/show?id=' . $employee->getKey());
    }

    /**
     * Update a linked employee login.
     */
    public function update(Request $request): SymfonyResponse
    {
        $employee = $this->employee($request);
        $login = $this->login($employee);

        $authValidity = AuthValidity::inject();
        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->unique('users', 'email', $login->getKey())->required()->toArray(),
            'locale' => $authValidity->locale()->required()->toArray(),
        ]);

        $login->forceFill([
            'email' => $validated->assertString('email'),
            'locale' => $validated->assertString('locale'),
        ])->save();

        $request->session()->flash('success', \__('Employee login updated.'));

        return \redirect('/employees/show?id=' . $employee->getKey());
    }

    /**
     * Set a linked employee login password manually.
     */
    public function password(Request $request): SymfonyResponse
    {
        $employee = $this->employee($request);
        $login = $this->login($employee);

        $authValidity = AuthValidity::inject();
        $validated = $this->validateRequest($request, [
            'password' => $authValidity->password()->required()->toArray(),
            'password_confirmation' => $authValidity->password()->required()->toArray(),
        ]);

        if ($validated->assertString('password') !== $validated->assertString('password_confirmation')) {
            Thrower::default()->message('password_confirmation', Typer::assertString(\__('auth.password_mismatch')))->throw();
        }

        DB::transaction(static function () use ($login, $validated): void {
            $login->forceFill([
                'password' => $validated->assertString('password'),
            ])->save();

            $login->databaseTokens()->getQuery()->delete();
        });

        $request->session()->flash('success', \__('Employee login password updated.'));

        return \redirect('/employees/show?id=' . $employee->getKey());
    }

    /**
     * Set a generated linked employee login password.
     */
    public function generatePassword(Request $request): SymfonyResponse
    {
        $employee = $this->employee($request);
        $login = $this->login($employee);
        $password = Str::password(16);

        DB::transaction(static function () use ($login, $password): void {
            $login->forceFill([
                'password' => $password,
            ])->save();

            $login->databaseTokens()->getQuery()->delete();
        });

        $request->session()->flash('success', \__('Employee login password generated.'));
        $request->session()->flash('employee_login_generated_password', $password);

        return \redirect('/employees/show?id=' . $employee->getKey());
    }

    /**
     * Delete a linked employee login while keeping the employee profile.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        $employee = $this->employee($request);
        $login = $this->login($employee);

        $login->delete();

        $request->session()->flash('success', \__('Employee login removed.'));

        return \redirect('/employees/show?id=' . $employee->getKey());
    }

    /**
     * Resolve the authorized employee profile.
     */
    private function employee(Request $request): EmployeeProfile
    {
        $actor = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $employee = ModelFinder::findOrAbort(EmployeeProfile::class, $id);

        Authorization::mustViewEmployee($actor, $employee);

        return $employee;
    }

    /**
     * Resolve the linked login account.
     */
    private function login(EmployeeProfile $employee): User
    {
        $employee->loadMissing('user');
        $login = $employee->getUser();

        if (!$login instanceof User || !$login->isEmployee()) {
            Thrower::default()->message('login', Typer::assertString(\__('Employee does not have a login account.')))->throw();
        }

        return $login;
    }
}
