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
        if ($user->isStoreManager()) {
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
        if ($user->isStoreManager()) {
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
        $schedule->loadMissing('store');
        $store = $schedule->getStore();

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
        $schedule->loadMissing('store');
        $store = $schedule->getStore();

        if (static::canManageStore($user, $store)) {
            return true;
        }

        if ($user->isEmployee() && $schedule->isPublished()) {
            return static::canViewStore($user, $store);
        }

        return false;
    }

    /**
     * Check whether the user can manage the given shift requirement.
     */
    public static function canManageShiftRequirement(User $user, ShiftRequirement $requirement): bool
    {
        $requirement->loadMissing('schedule');
        $schedule = $requirement->getSchedule();

        return static::canManageSchedule($user, $schedule);
    }

    /**
     * Get employees the user can manage.
     *
     * @return Builder<EmployeeProfile>
     */
    public static function managedEmployeesQuery(User $user): Builder
    {
        if ($user->isStoreManager()) {
            $user->loadMissing('managedStores');
            $storeIds = $user->managedStores->pluck('id')->all();

            /** @var Builder<EmployeeProfile> $builder */
            $builder = EmployeeProfile::query();

            return $builder->whereHas('stores', static function (Builder $q) use ($storeIds): void {
                $q->whereIn('stores.id', $storeIds);
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

    /**
     * Check whether the user can view the given employee profile.
     *
     * Store managers can view any employee they manage (i.e. any
     * employee that shares at least one of the manager's stores).
     * Employees can view their own profile.
     */
    public static function canViewEmployee(User $user, EmployeeProfile $employee): bool
    {
        if ($user->isStoreManager()) {
            $storeIds = static::managedStores($user)->pluck('id')->all();
            if (\count($storeIds) === 0) {
                return false;
            }

            $employee->loadMissing('stores');

            return $employee->stores->contains(static fn(Store $s): bool => \in_array($s->getKey(), $storeIds, true));
        }

        if ($user->isEmployee()) {
            $profile = $user->employeeProfile;

            return $profile instanceof EmployeeProfile &&
                $profile->getKey() === $employee->getKey();
        }

        return false;
    }

    /**
     * Throw if the user cannot view the given employee profile.
     */
    public static function mustViewEmployee(User $user, EmployeeProfile $employee): void
    {
        if (!static::canViewEmployee($user, $employee)) {
            throw new AccessDeniedHttpException('You cannot view this employee.');
        }
    }

    /**
     * Check whether the user can create a store.
     */
    public static function canCreateStore(User $user): bool
    {
        return $user->isStoreManager();
    }

    /**
     * Throw if the user cannot create a store.
     */
    public static function mustCreateStore(User $user): void
    {
        if (!static::canCreateStore($user)) {
            throw new AccessDeniedHttpException('You cannot create a store.');
        }
    }

    /**
     * Check whether the user can delete a store.
     */
    public static function canDeleteStore(User $user, Store $store): bool
    {
        return static::canManageStore($user, $store);
    }

    /**
     * Throw if the user cannot delete the given store.
     */
    public static function mustDeleteStore(User $user, Store $store): void
    {
        if (!static::canDeleteStore($user, $store)) {
            throw new AccessDeniedHttpException('You cannot delete this store.');
        }
    }

    /**
     * Check whether the user is a store manager at all.
     *
     * Several web controllers act only on store_manager sessions (the
     * AI agent and the manager-driven availability CRUD). Use this
     * helper instead of inlining `if (!$user->isStoreManager()) abort`
     * so the access-denied message and 403 status stay consistent.
     */
    public static function canBeStoreManager(User $user): bool
    {
        return $user->isStoreManager();
    }

    /**
     * Throw if the user is not a store manager.
     */
    public static function mustBeStoreManager(User $user): void
    {
        if (!static::canBeStoreManager($user)) {
            throw new AccessDeniedHttpException('You are not a store manager.');
        }
    }
}
