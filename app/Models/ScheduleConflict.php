<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConflictSeverityEnum;
use App\Enums\ConflictTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

class ScheduleConflict extends BaseModel
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
     * Scope to unresolved conflicts.
     *
     * @param Builder<static> $builder
     */
    public static function scopeUnresolved(Builder $builder): void
    {
        $builder->getQuery()->whereNull($builder->qualifyColumn('resolved_at'));
    }

    /**
     * Schedule id getter.
     */
    public function getScheduleId(): int
    {
        return $this->assertInt('schedule_id');
    }

    /**
     * Type getter.
     */
    public function getType(): ConflictTypeEnum
    {
        return ConflictTypeEnum::from($this->assertString('type'));
    }

    /**
     * Severity getter.
     */
    public function getSeverity(): ConflictSeverityEnum
    {
        return ConflictSeverityEnum::from($this->assertString('severity'));
    }

    /**
     * Message getter.
     */
    public function getMessage(): string
    {
        return $this->assertString('message');
    }

    /**
     * Suggested fix getter.
     */
    public function getSuggestedFix(): string|null
    {
        return $this->assertNullableString('suggested_fix');
    }

    /**
     * Resolved at getter.
     */
    public function getResolvedAt(): \Illuminate\Support\Carbon|null
    {
        return $this->assertNullableCarbon('resolved_at');
    }

    /**
     * Is resolved.
     */
    public function isResolved(): bool
    {
        return $this->getResolvedAt() !== null;
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
     * Shift requirement relationship.
     *
     * @return BelongsTo<ShiftRequirement, $this>
     */
    public function shiftRequirement(): BelongsTo
    {
        return $this->belongsTo(ShiftRequirement::class);
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
     * Employee profile id getter.
     */
    public function getEmployeeProfileId(): int|null
    {
        return $this->assertNullableInt('employee_profile_id');
    }

    /**
     * Shift requirement id getter.
     */
    public function getShiftRequirementId(): int|null
    {
        return $this->assertNullableInt('shift_requirement_id');
    }

    /**
     * Whether the conflict is critical.
     */
    public function isCritical(): bool
    {
        return $this->getSeverity() === ConflictSeverityEnum::Critical;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
