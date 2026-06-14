# "Return value must be of type string, null returned" — env key in the error

## Symptom

- A trait method like `Env::assertString('KEY')` or `Env::mustParseString('KEY')`
  is called for a key that is missing from the runtime environment.
- In production (`zend.assertions=-1`), PHP throws a bare
  `TypeError: Thinkycz\LaravelCore\Support\Env::mustParseString(): Return value
must be of type string, null returned` with no hint about which key was
  missing or why the function returned null.

## Root cause

The original `AssertTrait` and `ParseTrait` use `\assert(condition, message)`:

```php
public function mustParseString(string $key): string
{
    $value = $this->mustParseNullableString($key);
    \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));
    return $value;
}
```

`\assert()` becomes a **no-op** when `zend.assertions=-1` (the production
default). The function returns `null`, and the `: string` return type
triggers a generic `TypeError` that does not name the env key.

When `zend.assertions=1` (PHPStan, tests), the `\assert` does fire and
the original `Panicker::message` payload already contains the key as a
JSON-encoded arg — the message format `[Method] - assertion failed |
{"key":"APP_ENV","value":null}` does include the key, just buried.

So the same missing key produces:

- **dev/test**: a clear panic with the key in the args
- **production**: a bare TypeError that says nothing about the key

## Fix

Replace every `\assert(condition, Panicker::message(...))` with an explicit
`if (!condition) Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' <description>', \compact('key', 'value'));`.

The check now runs unconditionally, the message starts with the env key
in plain text, and PHPStan narrows the type after the `if` block, so no
PHPDoc `@var` is needed.

Production panic format:

```
[Thinkycz\LaravelCore\Traits\ParseTrait::mustParseString] - env APP_ENV must not be null
  | key(string):"APP_ENV" value(null):null
```

The key appears twice — once in the message text and once in the args
section — and `get_debug_type($value)` tells you whether the env was
missing, empty, or the wrong type.

## Regression coverage

`tests/Unit/Env/EnvKeyInPanicTest.php` has 7 tests:

- 4 spawn a child PHP with `php -d zend.assertions=-1` and assert that
  `mustParseString` / `assertString` / `mustParseBool` / `mustParseInt`
  throw a `RuntimeException` (not a `TypeError`) that contains the env
  key in its message. The child-process approach is required because
  `zend.assertions` cannot be flipped via `ini_set` at runtime — PHP
  only allows it in `php.ini`.
- 2 in-process tests verify the panic also fires under the test
  framework's normal `zend.assertions=1` (proving the check is not
  a no-op anymore).
- 1 happy-path test pins the "key set" behavior so the panic doesn't
  fire when the env actually has the value.

## Takeaway

- `\assert()` is silently stripped in production. Any `\assert(...)-then-
return-$value` pattern can return a type-incompatible value at
  runtime. Use explicit `if (!...) Panicker::panic(...)` for the checks
  that gate return types.
- `zend.assertions` can only be set in `php.ini` (not `ini_set`).
  Regression tests for "assertion off" behavior must shell out to a
  child PHP with `-d zend.assertions=-1`.
- The error-message prefix pattern — `'env ' . Typer::assertString($key)
. ' '` — keeps the env key visible at the very start of the panic
  message, ahead of the type context. Useful when greping logs for
  missing-env failures.
