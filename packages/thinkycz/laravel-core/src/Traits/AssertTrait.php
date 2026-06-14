<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Traits;

use BackedEnum;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Panicker;
use Thinkycz\LaravelCore\Support\Typer;

trait AssertTrait
{
    /**
     * Assert string.
     */
    public function assertString(string $key): string
    {
        $value = $this->mixed($key);

        if (!\is_string($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected string, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable string.
     */
    public function assertNullableString(string $key): string|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !\is_string($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected string or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert bool.
     */
    public function assertBool(string $key): bool
    {
        $value = $this->mixed($key);

        if (!\is_bool($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected bool, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable bool.
     */
    public function assertNullableBool(string $key): bool|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !\is_bool($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected bool or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert int.
     */
    public function assertInt(string $key): int
    {
        $value = $this->mixed($key);

        if (!\is_int($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected int, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable int.
     */
    public function assertNullableInt(string $key): int|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !\is_int($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected int or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert float.
     */
    public function assertFloat(string $key): float
    {
        $value = $this->mixed($key);

        if (!\is_float($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected float, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable float.
     */
    public function assertNullableFloat(string $key): float|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !\is_float($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected float or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert array.
     *
     * @return array<mixed>
     */
    public function assertArray(string $key): array
    {
        $value = $this->mixed($key);

        if (!\is_array($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected array, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable array.
     *
     * @return array<mixed>|null
     */
    public function assertNullableArray(string $key): array|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !\is_array($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected array or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert file.
     */
    public function assertFile(string $key): UploadedFile
    {
        $value = $this->mixed($key);

        \assert($value instanceof UploadedFile, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable file.
     */
    public function assertNullableFile(string $key): UploadedFile|null
    {
        $value = $this->mixed($key);

        \assert($value === null || $value instanceof UploadedFile, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert carbon.
     */
    public function assertCarbon(string $key): Carbon
    {
        $value = $this->mixed($key);

        if (!$value instanceof Carbon) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected Carbon, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable carbon.
     */
    public function assertNullableCarbon(string $key): Carbon|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !($value instanceof Carbon)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected Carbon or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert object.
     */
    public function assertObject(string $key): object
    {
        $value = $this->mixed($key);

        if (!\is_object($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected object, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable object.
     */
    public function assertNullableObject(string $key): object|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !\is_object($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected object or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert scalar.
     */
    public function assertScalar(string $key): bool|float|int|string
    {
        $value = $this->mixed($key);

        if (!\is_scalar($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected scalar, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert nullable scalar.
     */
    public function assertNullableScalar(string $key): bool|float|int|string|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !\is_scalar($value)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected scalar or null, got ' . \get_debug_type($value), \compact('key', 'value'));
        }

        return $value;
    }

    /**
     * Assert enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function assertEnum(string $key, string $enum): BackedEnum
    {
        $value = $this->mixed($key);

        if (!$value instanceof $enum) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected instance of ' . $enum . ', got ' . \get_debug_type($value), \compact('key', 'value', 'enum'));
        }

        return $value;
    }

    /**
     * Assert nullable enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public function assertNullableEnum(string $key, string $enum): BackedEnum|null
    {
        $value = $this->mixed($key);

        if ($value !== null && !($value instanceof $enum)) {
            Panicker::panic(__METHOD__, 'env ' . Typer::assertString($key) . ' expected instance of ' . $enum . ' or null, got ' . \get_debug_type($value), \compact('key', 'value', 'enum'));
        }

        return $value;
    }

    /**
     * Assert instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function assertInstance(string $key, string $class): object
    {
        $value = $this->mixed($key);

        if ($value instanceof $class) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert nullable instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function assertNullableInstance(string $key, string $class): object|null
    {
        $value = $this->mixed($key);

        if ($value === null || $value instanceof $class) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return class-string<T>
     */
    public function assertA(string $key, string $class): string
    {
        $value = $this->mixed($key);

        if (\is_string($value) && \is_a($value, $class, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert nullable a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return class-string<T>|null
     */
    public function assertNullableA(string $key, string $class): string|null
    {
        $value = $this->mixed($key);

        if ($value === null) {
            return $value;
        }

        if (\is_string($value) && \is_a($value, $class, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert in.
     *
     * @template T
     *
     * @param array<T> $enum
     *
     * @return T
     */
    public function assertIn(string $key, array $enum): mixed
    {
        $value = $this->mixed($key);

        if (\in_array($value, $enum, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'enum'));
    }

    /**
     * Assert not null.
     *
     * @return array<mixed>|bool|float|int|object|string
     */
    public function assertNotNull(string $key): array|bool|float|int|object|string
    {
        $value = $this->mixed($key);

        \assert(
            \is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value) || \is_object($value) || \is_array($value),
            Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')),
        );

        return $value;
    }

    /**
     * Assert null.
     */
    public function assertNull(string $key): mixed
    {
        $value = $this->mixed($key);

        if ($value !== null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value'));
        }

        return null;
    }

    /**
     * Assert BigDecimal.
     */
    public function assertBigDecimal(string $key): BigDecimal
    {
        $value = $this->mixed($key);

        if ($value instanceof BigDecimal) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value'));
    }

    /**
     * Assert nullable BigDecimal.
     */
    public function assertNullableBigDecimal(string $key): BigDecimal|null
    {
        $value = $this->mixed($key);

        if ($value === null || $value instanceof BigDecimal) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value'));
    }
}
