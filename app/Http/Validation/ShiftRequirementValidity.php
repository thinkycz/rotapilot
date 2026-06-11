<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\ShiftSourceEnum;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ShiftRequirementValidity
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
            'start_time' => [$this->startTime()],
            'end_time' => [$this->endTime()],
            'required_employee_count' => [$this->requiredEmployeeCount()],
            'role_label' => [$this->roleLabel()],
            'note' => [$this->note()],
            'source' => [$this->source()],
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
            'start_time' => [$this->startTime()],
            'end_time' => [$this->endTime()],
            'required_employee_count' => [$this->requiredEmployeeCount()],
            'role_label' => [$this->roleLabel()],
            'note' => [$this->note()],
            'source' => [$this->source()],
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
     * Required count validation.
     */
    public function requiredEmployeeCount(): Validity
    {
        return $this->baseValidity->make()->integer(50, 1);
    }

    /**
     * Role label validation.
     */
    public function roleLabel(): Validity
    {
        return $this->baseValidity->make()->string(128);
    }

    /**
     * Note validation.
     */
    public function note(): Validity
    {
        return $this->baseValidity->make()->string(2048);
    }

    /**
     * Source validation.
     */
    public function source(): Validity
    {
        return $this->baseValidity->make()->inString(ShiftSourceEnum::values());
    }
}
