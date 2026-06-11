<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class RegisterController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the registration page.
     */
    public function create(): RedirectResponse|Response
    {
        if (User::auth() instanceof User) {
            return Resolver::resolveRedirector()->to('/dashboard');
        }

        return Inertia::render('auth/Register');
    }

    /**
     * Register a new store manager. Public registration is restricted to the
     * store_manager role. Employees are created by managers; admins are
     * provisioned by seeders or tinker.
     */
    public function store(Request $request): SymfonyResponse
    {
        $this->hit($this->limit());

        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->unique('users', 'email')->required()->toArray(),
            'password' => $authValidity->password()->required()->confirmed()->toArray(),
            'locale' => $authValidity->locale()->required()->toArray(),
        ]);

        $user = DB::transaction(static function () use ($validated): User {
            return User::create([
                'email' => $validated->assertString('email'),
                'locale' => $validated->assertString('locale'),
                'password' => $validated->assertString('password'),
                'role' => UserRoleEnum::StoreManager->value,
                'is_active' => true,
            ]);
        });

        Resolver::resolveDatabaseTokenGuard('users')->login($user);

        $request->session()->regenerate();

        return Resolver::resolveRedirector()->to('/dashboard');
    }
}
