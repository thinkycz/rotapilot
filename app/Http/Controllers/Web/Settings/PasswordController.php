<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class PasswordController
{
    use ValidatesWebRequests;

    /**
     * Show password settings.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): Response
    {
        $user = User::mustAuth();
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'password' => $authValidity->password()->required()->toArray(),
            'new_password' => $authValidity->password()->required()->toArray(),
            'new_password_confirmation' => $authValidity->password()->required()->toArray(),
        ]);

        if ($validated->assertString('new_password') !== $validated->assertString('new_password_confirmation')) {
            throw ValidationException::withMessages([
                'new_password_confirmation' => \__('auth.password_mismatch'),
            ]);
        }

        $hasher = Resolver::resolveHasher();

        if (!$hasher->check($validated->assertString('password'), $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password' => \__('auth.password'),
            ]);
        }

        $user->forceFill([
            'password' => $validated->assertString('new_password'),
        ])->save();

        $user->databaseTokens()->getQuery()->delete();

        $request->session()->flash('success', \__('Password updated.'));

        return Inertia::render('settings/Password');
    }
}
