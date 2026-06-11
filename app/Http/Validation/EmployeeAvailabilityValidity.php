<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\AvailabilitySourceEnum;
use App\Enums\AvailabilityTypeEnum;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class EmployeeAvailabilityValidity
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
     * Create rules.
     *
     * @return array<string, mixed>
     */
    public function create(): array
    {
        return [
            'date' => [$this->date()],
            'type' => [$this->type()],
            'source' => [$this->source()],
            'start_time' => [$this->startTime()],
            'end_time' => [$this->endTime()],
            'note' => [$this->note()],
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
            'date' => [$this->date()],
            'type' => [$this->type()],
            'source' => [$this->source()],
            'start_time' => [$this->startTime()],
            'end_time' => [$this->endTime()],
            'note' => [$this->note()],
        ];
    }

    /**
     * Date validation.
     */
    public function date(): Validity
    {
        return $this->baseValidity->make()->date();
    }

    /**
     * Type validation.
     */
    public function type(): Validity
    {
        return $this->baseValidity->make()->inString(AvailabilityTypeEnum::values());
    }

    /**
     * Source validation.
     */
    public function source(): Validity
    {
        return $this->baseValidity->make()->inString(AvailabilitySourceEnum::values());
    }

    /**
     * Start time validation.
     */
    public function startTime(): Validity
    {
        return $this->baseValidity->make()->dateFormat('H:i');
    }

    /**
     * End time validation.
     */
    public function endTime(): Validity
    {
        return $this->baseValidity->make()->dateFormat('H:i');
    }

    /**
     * Note validation.
     */
    public function note(): Validity
    {
        return $this->baseValidity->make()->string(2048);
    }
}
