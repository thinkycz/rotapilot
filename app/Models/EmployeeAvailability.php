<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AvailabilitySourceEnum;
use App\Enums\AvailabilityTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class EmployeeAvailability extends BaseModel
{
    /**
     * Base select query.
     *
     * @param Builder<static> $builder
     */
    public static function querySelect(Builder $builder): void
    {
        $builder->getQuery()->select($builder->qualifyColumn('*'));
    }

    /**
     * Search scope.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->getQuery()->where($builder->qualifyColumn('id'), (int) $search);
    }

    /**
     * Employee profile id getter.
     */
    public function getEmployeeProfileId(): int
    {
        return $this->assertInt('employee_profile_id');
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int|null
    {
        return $this->assertNullableInt('store_id');
    }

    /**
     * Date getter.
     */
    public function getDate(): string
    {
        return $this->assertString('date');
    }

    /**
     * Start time getter.
     */
    public function getStartTime(): string|null
    {
        return $this->assertNullableString('start_time');
    }

    /**
     * End time getter.
     */
    public function getEndTime(): string|null
    {
        return $this->assertNullableString('end_time');
    }

    /**
     * Type getter.
     */
    public function getType(): AvailabilityTypeEnum
    {
        return AvailabilityTypeEnum::from($this->assertString('type'));
    }

    /**
     * Note getter.
     */
    public function getNote(): string|null
    {
        return $this->assertNullableString('note');
    }

    /**
     * Source getter.
     */
    public function getSource(): AvailabilitySourceEnum
    {
        return AvailabilitySourceEnum::from($this->assertString('source'));
    }

    /**
     * Created by getter.
     */
    public function getCreatedBy(): int|null
    {
        return $this->assertNullableInt('created_by');
    }

    /**
     * Employee profile relationship.
     *
     * @return BelongsTo<EmployeeProfile, $this>
     */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    /**
     * Store relationship.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Store getter.
     */
    public function getStore(): Store|null
    {
        if (!$this->relationLoaded('store')) {
            return null;
        }

        return Typer::assertNullableInstance($this->getRelationValue('store'), Store::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
