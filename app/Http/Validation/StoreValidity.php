<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class StoreValidity
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
     * Address validation.
     */
    public function address(): Validity
    {
        return $this->baseValidity->make()->string(1024);
    }

    /**
     * City validation.
     */
    public function city(): Validity
    {
        return $this->baseValidity->make()->string(255);
    }

    /**
     * Timezone validation.
     */
    public function timezone(): Validity
    {
        return $this->baseValidity->make()->varchar(64)->inString(\timezone_identifiers_list());
    }

    /**
     * Is active validation.
     */
    public function isActive(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }
}
