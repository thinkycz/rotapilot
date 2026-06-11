<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

class Store extends BaseModel
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
        $builder->getQuery()->where($builder->qualifyColumn('name'), 'LIKE', "%{$search}%");
    }

    /**
     * Scope active.
     *
     * @param Builder<static> $builder
     */
    public static function scopeActive(Builder $builder): void
    {
        $builder->getQuery()->where($builder->qualifyColumn('is_active'), true);
    }

    /**
     * Name getter.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Address getter.
     */
    public function getAddress(): string|null
    {
        return $this->assertNullableString('address');
    }

    /**
     * City getter.
     */
    public function getCity(): string|null
    {
        return $this->assertNullableString('city');
    }

    /**
     * Timezone getter.
     */
    public function getTimezone(): string
    {
        return $this->assertString('timezone');
    }

    /**
     * Is active getter.
     */
    public function getIsActive(): bool
    {
        return $this->assertBool('is_active');
    }

    /**
     * Business hours relationship.
     *
     * @return HasMany<StoreBusinessHour, $this>
     */
    public function businessHours(): HasMany
    {
        return $this->hasMany(StoreBusinessHour::class);
    }

    /**
     * Business hours collection, keyed by day_of_week.
     *
     * @return Collection<int, StoreBusinessHour>
     */
    public function getBusinessHours(): Collection
    {
        /** @var Collection<int, StoreBusinessHour> $hours */
        $hours = $this->businessHours()->get();

        return $hours->sortBy('day_of_week')->values();
    }

    /**
     * Find the business hour for a given day of week (1=Monday..7=Sunday).
     */
    public function findBusinessHourFor(int $dayOfWeek): StoreBusinessHour|null
    {
        return $this->businessHours()
            ->where('day_of_week', $dayOfWeek)
            ->first();
    }

    /**
     * Managers relationship.
     *
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_manager_store')->withTimestamps();
    }

    /**
     * Employees relationship.
     *
     * @return BelongsToMany<EmployeeProfile, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeProfile::class, 'employee_store')->withTimestamps();
    }

    /**
     * Schedules relationship.
     *
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
