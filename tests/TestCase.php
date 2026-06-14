<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cached Inertia asset version for the current test process.
     */
    protected static string|null $inertiaVersion = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Creates the application.
     *
     * Forcing APP_ENV=testing before bootstrap ensures two things:
     *   - The container's `env` binding is 'testing', so Laravel's
     *     `runningUnitTests()` check returns true and the standard
     *     CSRF middleware (`PreventRequestForgery::runningUnitTests()`)
     *     short-circuits instead of throwing TokenMismatchException.
     *   - Environment-specific .env files (e.g. .env.testing) are
     *     not silently re-introducing APP_ENV=local from this
     *     repo's .env.testing override.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        \putenv('APP_ENV=testing');

        return parent::createApplication();
    }

    /**
     * Headers for an Inertia JSON page request.
     *
     * @return array<string, string>
     */
    protected function inertiaHeaders(): array
    {
        if (static::$inertiaVersion === null) {
            $manifest = \public_path('build/manifest.json');

            if (\is_file($manifest)) {
                $hash = \hash_file('xxh128', $manifest);

                static::$inertiaVersion = \is_string($hash) ? $hash : 'fallback';
            } else {
                static::$inertiaVersion = 'fallback';
            }
        }

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => static::$inertiaVersion,
        ];
    }
}
