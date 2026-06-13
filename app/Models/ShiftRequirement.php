<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Enums\ShiftSourceEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

class ShiftRequirement extends BaseModel
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
     * Schedule id getter.
     */
    public function getScheduleId(): int
    {
        return $this->assertInt('schedule_id');
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Date getter.
     */
    public function getDate(): string
    {
        $value = $this->mixed('date');

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $this->assertString('date');
    }

    /**
     * Start time getter.
     */
    public function getStartTime(): string
    {
        return $this->assertString('start_time');
    }

    /**
     * End time getter.
     */
    public function getEndTime(): string
    {
        return $this->assertString('end_time');
    }

    /**
     * Role label getter.
     */
    public function getRoleLabel(): string|null
    {
        return $this->assertNullableString('role_label');
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
    public function getSource(): ShiftSourceEnum
    {
        return ShiftSourceEnum::from($this->assertString('source'));
    }

    /**
     * Created by getter.
     */
    public function getCreatedBy(): int
    {
        return $this->assertInt('created_by');
    }

    /**
     * Schedule relationship.
     *
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Schedule getter.
     */
    public function getSchedule(): Schedule
    {
        return $this->assertRelationship('schedule', Schedule::class);
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
     * Assignments relationship.
     *
     * @return HasMany<ShiftAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Assigned count.
     */
    public function getAssignedCount(): int
    {
        $assignments = $this->assignments()
            ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
            ->get();

        return \count($assignments);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
