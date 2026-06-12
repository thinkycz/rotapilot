<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Enums\GuardEnum;
use App\Enums\UserRoleEnum;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thinkycz\LaravelCore\Exceptions\GenericHttpException;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class RegisterController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        if ($validated->assertString('password') !== $request->input('password_confirmation')) {
            $msg = \__('auth.password_mismatch');
            throw new UnprocessableEntityHttpException(\is_string($msg) ? $msg : 'Password mismatch');
        }

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultGuard();

        if ($guard === GuardEnum::USERS->value) {
            $user = User::create([
                'email' => $validated->assertString('email'),
                'locale' => $validated->assertString('locale'),
                'password' => $validated->assertString('password'),
                'role' => UserRoleEnum::StoreManager->value,
                'is_active' => true,
            ]);
        } else {
            throw GenericHttpException::unauthorized();
        }

        Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);

        $user->refresh();

        return $user->meResource()->response();
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        $authValidity = AuthValidity::inject();

        return $request->builder()
            ->rules([
                'email' => $authValidity->email()->unique('users', 'email')->required(),
                'password' => $authValidity->password()->required()->confirmed(),
                'password_confirmation' => $authValidity->password()->required(),
                'locale' => $authValidity->locale()->required(),
            ])
            ->guard(GuardEnum::values())
            ->jsonApi()
            ->validate();
    }

    /**
     * Get the default guard name.
     */
    protected function getDefaultGuard(): string
    {
        return Config::inject()->assertString('auth.defaults.guard');
    }
}
