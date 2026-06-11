<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Enums\ShiftSourceEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

/**
 * @property int $id
 * @property int $shift_requirement_id
 * @property int $employee_profile_id
 * @property string $status
 * @property string $source
 * @property int|null $assigned_by
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ShiftAssignment extends BaseModel
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
     * Shift requirement id getter.
     */
    public function getShiftRequirementId(): int
    {
        return $this->assertInt('shift_requirement_id');
    }

    /**
     * Employee profile id getter.
     */
    public function getEmployeeProfileId(): int
    {
        return $this->assertInt('employee_profile_id');
    }

    /**
     * Status getter.
     */
    public function getStatus(): ShiftAssignmentStatusEnum
    {
        return ShiftAssignmentStatusEnum::from($this->assertString('status'));
    }

    /**
     * Source getter.
     */
    public function getSource(): ShiftSourceEnum
    {
        return ShiftSourceEnum::from($this->assertString('source'));
    }

    /**
     * Assigned by getter.
     */
    public function getAssignedBy(): int|null
    {
        return $this->assertNullableInt('assigned_by');
    }

    /**
     * Note getter.
     */
    public function getNote(): string|null
    {
        return $this->assertNullableString('note');
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
     * Shift requirement getter.
     */
    public function getShiftRequirement(): ShiftRequirement
    {
        return $this->assertRelationship('shiftRequirement', ShiftRequirement::class);
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
     * Employee profile getter.
     */
    public function getEmployeeProfile(): EmployeeProfile
    {
        return $this->assertRelationship('employeeProfile', EmployeeProfile::class);
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
