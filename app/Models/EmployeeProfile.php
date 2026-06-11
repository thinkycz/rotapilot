<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

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
     * Active employees.
     *
     * @param Builder<static> $builder
     */
    public static function scopeActive(Builder $builder): void
    {
        $builder->getQuery()->where($builder->qualifyColumn('is_active'), true);
    }

    /**
     * Employees assigned to a given store.
     *
     * @param Builder<static> $builder
     */
    public static function scopeForStore(Builder $builder, int $storeId): void
    {
        $builder->getQuery()->whereIn(
            $builder->qualifyColumn('id'),
            static fn($sub) => $sub
                ->select('employee_profile_id')
                ->from('employee_store')
                ->where('store_id', $storeId),
        );
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
        if (!$this->relationLoaded('user')) {
            return null;
        }

        return Typer::assertNullableInstance($this->getRelationValue('user'), User::class);
    }

    /**
     * Whether the employee profile has a linked login account.
     */
    public function hasLoginAccount(): bool
    {
        return $this->getUserId() !== null;
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
