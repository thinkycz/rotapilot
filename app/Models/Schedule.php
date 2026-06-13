<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduleStatusEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

class Schedule extends BaseModel
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
     * Store id getter.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Name getter.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Period start getter.
     */
    public function getPeriodStart(): string
    {
        $value = $this->mixed('period_start');

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $this->assertString('period_start');
    }

    /**
     * Period end getter.
     */
    public function getPeriodEnd(): string
    {
        $value = $this->mixed('period_end');

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $this->assertString('period_end');
    }

    /**
     * Status getter.
     */
    public function getStatus(): ScheduleStatusEnum
    {
        return ScheduleStatusEnum::from($this->assertString('status'));
    }

    /**
     * Created by getter.
     */
    public function getCreatedBy(): int
    {
        return $this->assertInt('created_by');
    }

    /**
     * Published at getter.
     */
    public function getPublishedAt(): \Illuminate\Support\Carbon|null
    {
        return $this->assertNullableCarbon('published_at');
    }

    /**
     * Is published.
     */
    public function isPublished(): bool
    {
        return $this->getStatus() === ScheduleStatusEnum::Published;
    }

    /**
     * Is draft.
     */
    public function isDraft(): bool
    {
        return $this->getStatus() === ScheduleStatusEnum::Draft;
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
    public function getStore(): Store
    {
        return $this->assertRelationship('store', Store::class);
    }

    /**
     * Creator relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Shift requirements relationship.
     *
     * @return HasMany<ShiftRequirement, $this>
     */
    public function shiftRequirements(): HasMany
    {
        return $this->hasMany(ShiftRequirement::class);
    }

    /**
     * Shift requirements getter.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int|string, ShiftRequirement>
     */
    public function getShiftRequirements(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->assertRelationshipCollection('shiftRequirements', ShiftRequirement::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'published_at' => 'datetime',
        ];
    }
}
