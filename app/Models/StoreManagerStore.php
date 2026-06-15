<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

class StoreManagerStore extends BaseModel
{
    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Store id getter.
     */
    public function getStoreId(): int
    {
        return $this->assertInt('store_id');
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
