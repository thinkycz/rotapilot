<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => Config::inject()->assertString('app.name'),
                'locale' => Config::inject()->assertString('app.locale'),
                'locales' => Config::inject()->assertArray('app.locales'),
            ],
            'auth' => [
                'user' => fn(): array|null => $this->user(),
            ],
            'flash' => [
                'success' => static fn(): string|null => self::flashMessage($request, 'success'),
                'error' => static fn(): string|null => self::flashMessage($request, 'error'),
                'shift_modal_success' => static fn(): string|null => self::flashMessage($request, 'shift_modal_success'),
                'shift_modal_error' => static fn(): string|null => self::flashMessage($request, 'shift_modal_error'),
                'create_shift_modal_success' => static fn(): string|null => self::flashMessage($request, 'create_shift_modal_success'),
                'create_shift_modal_error' => static fn(): string|null => self::flashMessage($request, 'create_shift_modal_error'),
                'availability_modal_success' => static fn(): string|null => self::flashMessage($request, 'availability_modal_success'),
                'availability_modal_error' => static fn(): string|null => self::flashMessage($request, 'availability_modal_error'),
                'employee_login_generated_password' => static fn(): string|null => self::flashMessage($request, 'employee_login_generated_password'),
            ],
            'conversations' => fn(): array => $this->agentConversations(),
        ];
    }

    /**
     * Resolve a flash message by key.
     *
     * Inertia v3 stores flash data under the dedicated `inertia.flash_data`
     * session key (see {@see Inertia::flash()}) and the Inertia middleware
     * reflashes the entry on every request. The Laravel session
     * `->flash('success', ...)` mechanism, by contrast, is consumed after a
     * single request and dies across an intermediate 302 redirect chain
     * (e.g. the authenticated visitor being bounced from /login to
     * /dashboard). We prefer the Inertia path so flashes survive the
     * 302 → guest-redirect → final render hop, and fall back to the
     * plain session key for same-request controllers that still use
     * `$request->session()->flash(...)`.
     */
    protected static function flashMessage(Request $request, string $key): string|null
    {
        $inertiaFlash = Inertia::getFlashed($request);

        if (isset($inertiaFlash[$key]) && \is_string($inertiaFlash[$key])) {
            return $inertiaFlash[$key];
        }

        return Typer::assertNullableString($request->session()->get($key));
    }

    /**
     * Resolve the authenticated user for shared Inertia props.
     *
     * @return array<string, mixed>|null
     */
    protected function user(): array|null
    {
        $user = Resolver::resolveAuthManager()->guard('users')->user();

        if ($user instanceof User === false) {
            return null;
        }

        return [
            'id' => $user->getKey(),
            'email' => $user->getEmail(),
            'locale' => $user->getLocale(),
            'email_verified_at' => $user->getEmailVerifiedAt()?->toJSON(),
            'role' => $user->getRole()->value,
            'is_active' => $user->getIsActive(),
        ];
    }

    /**
     * Get the latest 20 conversations for the authenticated user, if they are a manager.
     *
     * @return array<int, array<string, mixed>>
     */
    private function agentConversations(): array
    {
        $user = Resolver::resolveAuthManager()->guard('users')->user();

        if ($user instanceof User === false || !$user->isStoreManager()) {
            return [];
        }

        return Conversation::query()
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get()
            ->map(static function (Conversation $c): array {
                $id = Typer::assertString($c->getAttribute('id'));
                $title = Typer::assertString($c->getAttribute('title'));
                $updatedAt = Typer::assertNullableCarbon($c->getAttribute('updated_at'));

                return [
                    'id' => $id,
                    'title' => $title,
                    'updated_at' => $updatedAt !== null ? $updatedAt->toIso8601String() : null,
                ];
            })
            ->all();
    }
}
