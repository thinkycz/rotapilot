<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

/**
 * @property int $id
 * @property int $store_id
 * @property int $day_of_week
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property bool $is_closed
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class StoreBusinessHour extends BaseModel
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
     * Store id getter.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
    }

    /**
     * Day of week getter (1=Monday..7=Sunday).
     */
    public function getDayOfWeek(): int
    {
        return $this->assertInt('day_of_week');
    }

    /**
     * Opens at getter.
     */
    public function getOpensAt(): string|null
    {
        return $this->assertNullableString('opens_at');
    }

    /**
     * Closes at getter.
     */
    public function getClosesAt(): string|null
    {
        return $this->assertNullableString('closes_at');
    }

    /**
     * Is closed getter.
     */
    public function getIsClosed(): bool
    {
        return $this->assertBool('is_closed');
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
        ];
    }
}
