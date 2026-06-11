<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRoleEnum;
use App\Http\Resources\UserResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Carbon;
use Laravel\Ai\Concerns\HasConversations;
use Thinkycz\LaravelCore\Models\BaseUser;

class User extends BaseUser implements MustVerifyEmail
{
    use HasConversations;

    /**
     * Email getter.
     */
    public function getEmail(): string
    {
        return $this->assertString('email');
    }

    /**
     * Locale getter.
     */
    public function getLocale(): string
    {
        return $this->assertString('locale');
    }

    /**
     * EmailVerifiedAt getter.
     */
    public function getEmailVerifiedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('email_verified_at');
    }

    /**
     * Role getter.
     */
    public function getRole(): UserRoleEnum
    {
        return UserRoleEnum::from($this->assertString('role'));
    }

    /**
     * Is active getter.
     */
    public function getIsActive(): bool
    {
        return $this->assertBool('is_active');
    }

    /**
     * Is admin.
     */
    public function isAdmin(): bool
    {
        return $this->getRole() === UserRoleEnum::Admin;
    }

    /**
     * Is store manager.
     */
    public function isStoreManager(): bool
    {
        return $this->getRole() === UserRoleEnum::StoreManager;
    }

    /**
     * Is employee.
     */
    public function isEmployee(): bool
    {
        return $this->getRole() === UserRoleEnum::Employee;
    }

    /**
     * @inheritDoc
     */
    public function markEmailAsUnverified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => null,
        ])->save();
    }

    /**
     * Me resource.
     */
    public function meResource(): JsonApiResource
    {
        return new UserResource($this);
    }

    /**
     * VND json:api resource.
     */
    public function resource(): JsonApiResource
    {
        return $this->meResource();
    }

    /**
     * Managed stores relationship.
     *
     * @return BelongsToMany<Store, $this>
     */
    public function managedStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_manager_store')->withTimestamps();
    }

    /**
     * Employee profile relationship (if the user is an employee).
     *
     * @return HasOne<EmployeeProfile, $this>
     */
    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
