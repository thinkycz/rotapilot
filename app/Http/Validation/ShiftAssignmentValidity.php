<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ShiftAssignmentValidity
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
     * Employee profile id validation.
     */
    public function employeeProfileId(): Validity
    {
        return $this->baseValidity->make()->integer(null, 1);
    }

    /**
     * Start time validation.
     */
    public function startTime(): Validity
    {
        return $this->baseValidity->make()->string(null)->dateFormat('H:i');
    }

    /**
     * End time validation.
     */
    public function endTime(): Validity
    {
        return $this->baseValidity->make()->string(null)->dateFormat('H:i');
    }
}
