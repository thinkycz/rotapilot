<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Central authorization helpers. Used by controllers and policies.
 */
class Authorization
{
    /**
     * Get the stores the user can manage.
     *
     * @return Collection<int, Store>
     */
    public static function managedStores(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Db::hydrate(Store::query()->getQuery()->get(), Store::class)
                ->sortBy('name')
                ->values();
        }

        if ($user->isStoreManager()) {
            $user->loadMissing('managedStores');

            return $user->managedStores->sortBy('name')->values();
        }

        return new Collection();
    }

    /**
     * Check whether the user can manage the given store.
     */
    public static function canManageStore(User $user, Store $store): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->isStoreManager()) {
            return false;
        }

        $user->loadMissing('managedStores');

        return $user->managedStores->contains(fn(Store $s): bool => $s->getKey() === $store->getKey());
    }

    /**
     * Throw if the user cannot manage the given store.
     */
    public static function mustManageStore(User $user, Store $store): void
    {
        if (!static::canManageStore($user, $store)) {
            throw new AccessDeniedHttpException('You cannot manage this store.');
        }
    }

    /**
     * Check whether the user can view the given store.
     */
    public static function canViewStore(User $user, Store $store): bool
    {
        if ($user->isAdmin() || $user->isStoreManager()) {
            return static::canManageStore($user, $store);
        }

        if ($user->isEmployee()) {
            $profile = $user->employeeProfile;

            if (!$profile instanceof EmployeeProfile) {
                return false;
            }

            $profile->loadMissing('stores');

            return $profile->stores->contains(fn(Store $s): bool => $s->getKey() === $store->getKey());
        }

        return false;
    }

    /**
     * Get stores the user can view.
     *
     * @return Collection<int, Store>
     */
    public static function visibleStores(User $user): Collection
    {
        if ($user->isAdmin() || $user->isStoreManager()) {
            return static::managedStores($user);
        }

        if ($user->isEmployee()) {
            $profile = $user->employeeProfile;

            if (!$profile instanceof EmployeeProfile) {
                return new Collection();
            }

            $profile->loadMissing('stores');

            return $profile->stores->sortBy('name')->values();
        }

        return new Collection();
    }

    /**
     * Check whether the user can manage the given schedule.
     */
    public static function canManageSchedule(User $user, Schedule $schedule): bool
    {
        $store = $schedule->store;

        if (!$store instanceof Store) {
            return false;
        }

        return static::canManageStore($user, $store);
    }

    /**
     * Throw if the user cannot manage the given schedule.
     */
    public static function mustManageSchedule(User $user, Schedule $schedule): void
    {
        if (!static::canManageSchedule($user, $schedule)) {
            throw new AccessDeniedHttpException('You cannot manage this schedule.');
        }
    }

    /**
     * Check whether the user can view the given schedule.
     */
    public static function canViewSchedule(User $user, Schedule $schedule): bool
    {
        $store = $schedule->store;

        if ($store instanceof Store && static::canManageStore($user, $store)) {
            return true;
        }

        if ($user->isEmployee() && $schedule->isPublished() && $store instanceof Store) {
            return static::canViewStore($user, $store);
        }

        return false;
    }

    /**
     * Check whether the user can manage the given shift requirement.
     */
    public static function canManageShiftRequirement(User $user, ShiftRequirement $requirement): bool
    {
        $schedule = $requirement->schedule;

        if (!$schedule instanceof Schedule) {
            return false;
        }

        return static::canManageSchedule($user, $schedule);
    }

    /**
     * Get employees the user can manage.
     *
     * @return Builder<EmployeeProfile>
     */
    public static function managedEmployeesQuery(User $user): Builder
    {
        if ($user->isAdmin()) {
            return EmployeeProfile::query();
        }

        if ($user->isStoreManager()) {
            $user->loadMissing('managedStores');
            $storeIds = $user->managedStores->pluck('id')->all();

            /** @var Builder<EmployeeProfile> $builder */
            $builder = EmployeeProfile::query();

            return $builder->whereHas('stores', static function (Builder $q) use ($storeIds): void {
                $q->where('stores.id', $storeIds);
            });
        }

        /** @var Builder<EmployeeProfile> $builder */
        $builder = EmployeeProfile::query();

        return $builder->where('user_id', $user->getKey());
    }

    /**
     * Get the visible store options for the sidebar/dropdown.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function storeOptions(User $user): array
    {
        return static::visibleStores($user)
            ->map(static fn(Store $s): array => ['id' => $s->getKey(), 'name' => $s->getName()])
            ->values()
            ->all();
    }
}
