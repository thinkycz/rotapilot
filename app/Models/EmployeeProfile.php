<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $role_label
 * @property int|null $max_hours_per_week
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class EmployeeProfile extends BaseModel
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
     * User id getter.
     */
    public function getUserId(): int|null
    {
        return $this->assertNullableInt('user_id');
    }

    /**
     * Name getter.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Email getter.
     */
    public function getEmail(): string|null
    {
        return $this->assertNullableString('email');
    }

    /**
     * Phone getter.
     */
    public function getPhone(): string|null
    {
        return $this->assertNullableString('phone');
    }

    /**
     * Role label getter.
     */
    public function getRoleLabel(): string|null
    {
        return $this->assertNullableString('role_label');
    }

    /**
     * Max hours per week getter.
     */
    public function getMaxHoursPerWeek(): int|null
    {
        return $this->assertNullableInt('max_hours_per_week');
    }

    /**
     * Is active getter.
     */
    public function getIsActive(): bool
    {
        return $this->assertBool('is_active');
    }

    /**
     * User relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User getter.
     */
    public function getUser(): User|null
    {
        $value = $this->getAttribute('user');

        if ($value instanceof User) {
            return $value;
        }

        return null;
    }

    /**
     * Stores relationship.
     *
     * @return BelongsToMany<Store, $this>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'employee_store')->withTimestamps();
    }

    /**
     * Availabilities relationship.
     *
     * @return HasMany<EmployeeAvailability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(EmployeeAvailability::class);
    }

    /**
     * Shift assignments relationship.
     *
     * @return HasMany<ShiftAssignment, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_hours_per_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
