<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class ResetPasswordController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the reset password page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $request->string('token')->toString(),
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function store(Request $request): SymfonyResponse
    {
        $this->hit($this->limit());

        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->required()->toArray(),
            'password' => $authValidity->password()->required()->toArray(),
            'password_confirmation' => $authValidity->password()->required()->toArray(),
            'token' => $authValidity->passwordResetToken()->required()->toArray(),
        ]);

        if ($validated->assertString('password') !== $validated->assertString('password_confirmation')) {
            throw ValidationException::withMessages([
                'password_confirmation' => \__('auth.password_mismatch'),
            ]);
        }

        $user = Typer::assertNullableInstance(Resolver::resolveEloquentUserProvider('users')->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            throw ValidationException::withMessages([
                'email' => \__(PasswordBroker::INVALID_USER),
            ]);
        }

        $broker = Resolver::resolvePasswordBroker('users');

        if (!$broker->tokenExists($user, $validated->assertString('token'))) {
            throw ValidationException::withMessages([
                'token' => \__(PasswordBroker::INVALID_TOKEN),
            ]);
        }

        $user->forceFill([
            'password' => $validated->assertString('password'),
        ])->save();

        if ($user->getRememberToken() !== '') {
            Resolver::resolveEloquentUserProvider('users')->updateRememberToken($user, Str::random(60));
        }

        $user->databaseTokens()->getQuery()->delete();
        $broker->deleteToken($user);

        Resolver::resolveDatabaseTokenGuard('users')->login($user);

        return Resolver::resolveRedirector()->to('/dashboard');
    }
}
