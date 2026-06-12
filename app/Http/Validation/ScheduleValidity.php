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
     * Create rules.
     *
     * @return array<string, mixed>
     */
    public function create(): array
    {
        return [
            'name' => [$this->name()],
            'month' => [$this->month()],
            'year' => [$this->year()],
            'status' => [$this->status()],
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
            'name' => [$this->name()],
            'month' => [$this->month()],
            'year' => [$this->year()],
            'status' => [$this->status()],
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
     * Month validation.
     */
    public function month(): Validity
    {
        return $this->baseValidity->make()->integer(12, 1);
    }

    /**
     * Year validation.
     */
    public function year(): Validity
    {
        return $this->baseValidity->make()->integer(2100, 2000);
    }

    /**
     * Period start validation.
     */
    public function periodStart(): Validity
    {
        return $this->baseValidity->make()->string(null)->date();
    }

    /**
     * Period end validation.
     */
    public function periodEnd(): Validity
    {
        return $this->baseValidity->make()->string(null)->date();
    }

    /**
     * Status validation.
     */
    public function status(): Validity
    {
        return $this->baseValidity->make()->inString(ScheduleStatusEnum::values());
    }
}
