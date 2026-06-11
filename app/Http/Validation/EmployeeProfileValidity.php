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
}
