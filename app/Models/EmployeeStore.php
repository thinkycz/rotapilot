<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

/**
 * @property int $id
 * @property int $employee_profile_id
 * @property int $store_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class EmployeeStore extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string|null
     */
    protected $table = 'employee_store';

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
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
