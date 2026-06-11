<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\ScheduleStatusEnum;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ScheduleValidity
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
     * Period start validation.
     */
    public function periodStart(): Validity
    {
        return $this->baseValidity->make()->date();
    }

    /**
     * Period end validation.
     */
    public function periodEnd(): Validity
    {
        return $this->baseValidity->make()->date();
    }

    /**
     * Status validation.
     */
    public function status(): Validity
    {
        return $this->baseValidity->make()->inString(ScheduleStatusEnum::values());
    }
}
