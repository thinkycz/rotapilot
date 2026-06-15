<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class EmployeeProfileValidity
{
    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Inject a fresh instance.
     */
    public static function inject(): self
    {
        return new self();
    }

    /**
     * Coerce a request value to a nullable int.
     *
     * Accepts a real int, a numeric string ("12"), or null. Returns
     * null for anything else (including floats, booleans, and empty
     * strings) so the controller can use a single nullable-int pattern
     * without repeating the int/string/ctype_digit ladder inline.
     */
    public static function parseNullableInt(mixed $value): int|null
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && \ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Coerce a request value to a boolean, defaulting to $default when
     * the value is not an explicit bool.
     *
     * Mirrors the existing "default active" behaviour: the form
     * checkbox is omitted from the payload when unchecked, so the
     * controller must treat missing/null as the default rather than
     * as `false`. The validity rule already coerces "1"/"0"/1/0 to a
     * bool; this helper handles the post-validation "is it really a
     * bool?" check.
     */
    public static function parseBool(mixed $value, bool $default = false): bool
    {
        return \is_bool($value) ? $value : $default;
    }

    /**
     * Coerce a request store id to an int.
     *
     * Unlike parseNullableInt this never returns null — the controller
     * uses 0 as a sentinel and then looks the store up; the lookup
     * misses if the id was unparseable, which is the correct outcome.
     */
    public static function parseStoreId(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && \ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * Create rules.
     *
     * @return array<string, mixed>
     */
    public function create(): array
    {
        return [
            'user_id' => [$this->userId()],
            'name' => [$this->name()],
            'email' => [$this->email()],
            'phone' => [$this->phone()],
            'role_label' => [$this->roleLabel()],
            'max_hours_per_week' => [$this->maxHoursPerWeek()],
            'hourly_rate' => [$this->hourlyRate()],
            'is_active' => [$this->isActive()],
        ];
    }

    /**
     * Update rules.
     *
     * @return array<string, mixed>
     */
    public function update(): array
    {
        return [
            'user_id' => [$this->userId()],
            'name' => [$this->name()],
            'email' => [$this->email()],
            'phone' => [$this->phone()],
            'role_label' => [$this->roleLabel()],
            'max_hours_per_week' => [$this->maxHoursPerWeek()],
            'hourly_rate' => [$this->hourlyRate()],
            'is_active' => [$this->isActive()],
        ];
    }

    /**
     * Name validation.
     */
    public function name(): Validity
    {
        return $this->baseValidity->make()->varchar(255);
    }

    /**
     * Email validation.
     */
    public function email(): Validity
    {
        return $this->baseValidity->make()->varchar(255)->email();
    }

    /**
     * Phone validation.
     */
    public function phone(): Validity
    {
        return $this->baseValidity->make()->string(64);
    }

    /**
     * Role label validation.
     */
    public function roleLabel(): Validity
    {
        return $this->baseValidity->make()->string(128);
    }

    /**
     * Max hours per week validation.
     */
    public function maxHoursPerWeek(): Validity
    {
        return $this->baseValidity->make()->integer(168, 1);
    }

    /**
     * User id validation.
     */
    public function userId(): Validity
    {
        return $this->baseValidity->make()->integer(null, 1);
    }

    /**
     * Is active validation.
     */
    public function isActive(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }

    /**
     * Hourly rate validation.
     */
    public function hourlyRate(): Validity
    {
        return $this->baseValidity->make()->integer(999999, 0);
    }
}
