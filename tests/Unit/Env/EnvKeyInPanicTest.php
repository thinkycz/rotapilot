<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;

/**
 * Run the closure in a fresh PHP process with `zend.assertions=-1`
 * (the production default). When the trait's `\assert()` checks are
 * compiled out, the function returns null and PHP trips a generic
 * `TypeError: ... Return value must be of type string, null
 * returned` with no mention of the missing key.
 *
 * After the fix, the checks use `Typer::assert` which always
 * panics, so a missing key throws a `RuntimeException` whose
 * message includes the key regardless of `zend.assertions`.
 *
 * `zend.assertions` cannot be changed via `ini_set` at runtime —
 * PHP only allows flipping it in `php.ini`. So we shell out to a
 * child PHP that loads the same Laravel context, calls the trait
 * method, and prints either the panic message or the TypeError.
 *
 * @return array{0: string, 1: bool} 0 = stdout, 1 = true on panic
 */
function runWithAssertionsOff(string $code): array
{
    $script = <<<'PHP'
        <?php
        declare(strict_types=1);

        require $argv[1] . '/vendor/autoload.php';

        $app = require $argv[1] . '/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $code = $argv[2];

        try {
            eval($code);
            fwrite(\STDOUT, 'no-throw');
        } catch (\Throwable $e) {
            fwrite(\STDOUT, $e::class . '::' . $e->getMessage());
        }
        PHP;

    $project = \getcwd();
    $tmp = \tempnam(\sys_get_temp_dir(), 'env-panic-') . '.php';
    \file_put_contents($tmp, $script);

    try {
        $cmd = \sprintf(
            'APP_ENV=local php -d zend.assertions=-1 %s %s %s 2>&1',
            \escapeshellarg($tmp),
            \escapeshellarg($project),
            \escapeshellarg($code),
        );
        $output = (string) \shell_exec($cmd);

        return [$output, \str_contains($output, 'RuntimeException') && \str_contains($output, 'REGRESSION_MISSING_KEY_XYZ')];
    } finally {
        \unlink($tmp);
    }
}

\test('mustParseString panics with the env key when assertions are off in a child PHP process', function (): void {
    [$output, $ok] = \runWithAssertionsOff('\\Thinkycz\\LaravelCore\\Support\\Env::inject()->mustParseString("REGRESSION_MISSING_KEY_XYZ");');

    \expect($ok)->toBeTrue("Expected a Panicker panic in child process output:\n{$output}");
    \expect($output)->toContain('REGRESSION_MISSING_KEY_XYZ');
});

\test('assertString panics with the env key when assertions are off in a child PHP process', function (): void {
    [$output, $ok] = \runWithAssertionsOff('\\Thinkycz\\LaravelCore\\Support\\Env::inject()->assertString("REGRESSION_MISSING_KEY_XYZ");');

    \expect($ok)->toBeTrue("Expected a Panicker panic in child process output:\n{$output}");
    \expect($output)->toContain('REGRESSION_MISSING_KEY_XYZ');
});

\test('mustParseBool panics with the env key when assertions are off in a child PHP process', function (): void {
    [$output, $ok] = \runWithAssertionsOff('\\Thinkycz\\LaravelCore\\Support\\Env::inject()->mustParseBool("REGRESSION_MISSING_KEY_XYZ");');

    \expect($ok)->toBeTrue("Expected a Panicker panic in child process output:\n{$output}");
    \expect($output)->toContain('REGRESSION_MISSING_KEY_XYZ');
});

\test('mustParseInt panics with the env key when assertions are off in a child PHP process', function (): void {
    [$output, $ok] = \runWithAssertionsOff('\\Thinkycz\\LaravelCore\\Support\\Env::inject()->mustParseInt("REGRESSION_MISSING_KEY_XYZ");');

    \expect($ok)->toBeTrue("Expected a Panicker panic in child process output:\n{$output}");
    \expect($output)->toContain('REGRESSION_MISSING_KEY_XYZ');
});

\test('mustParseString always panics with the env key in the message', function (): void {
    // Run under the test framework's normal zend.assertions=1
    // (Pest sets it in phpunit.xml). The fix means the check now
    // uses Typer::assert, which runs unconditionally — so the
    // message below is produced regardless of zend.assertions.
    try {
        Env::inject()->mustParseString('REGRESSION_ALWAYS_KEY');
        \expect(false)->toBeTrue('Expected a panic but the call returned without throwing');
    } catch (RuntimeException $e) {
        \expect($e->getMessage())->toContain('REGRESSION_ALWAYS_KEY');
    }
});

\test('assertString always panics with the env key in the message', function (): void {
    try {
        Env::inject()->assertString('REGRESSION_ALWAYS_KEY');
        \expect(false)->toBeTrue('Expected a panic but the call returned without throwing');
    } catch (RuntimeException $e) {
        \expect($e->getMessage())->toContain('REGRESSION_ALWAYS_KEY');
    }
});

\test('happy path: assertString returns the value as-is when the key is set', function (): void {
    \putenv('REGRESSION_HAPPY_KEY=hello');

    try {
        $value = Env::inject()->assertString('REGRESSION_HAPPY_KEY');
        \expect($value)->toBe('hello');
    } finally {
        \putenv('REGRESSION_HAPPY_KEY');
    }
});
