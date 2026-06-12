<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\EmployeeProfileValidity;
use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EmployeeUpdateController
{
    use ValidatesWebRequests;

    /**
     * Update an employee.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $employee = ModelFinder::findOrAbort(EmployeeProfile::class, $id);

        Authorization::mustViewEmployee($actor, $employee);

        $validity = EmployeeProfileValidity::inject();
        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'email' => $validity->email()->nullable()->toArray(),
            'phone' => $validity->phone()->nullable()->toArray(),
            'role_label' => $validity->roleLabel()->nullable()->toArray(),
            'max_hours_per_week' => $validity->maxHoursPerWeek()->nullable()->toArray(),
            'is_active' => $validity->isActive()->nullable()->toArray(),
            'store_ids' => [
                'required',
                'array',
                'min:1',
                static function (string $attribute, mixed $value, Closure $fail) use ($actor): void {
                    if (!\is_array($value)) {
                        return;
                    }
                    foreach ($value as $storeId) {
                        $storeIdInt = \is_int($storeId) ? $storeId : (\is_string($storeId) && \ctype_digit($storeId) ? (int) $storeId : 0);
                        $store = Store::query()->find($storeIdInt);
                        if ($store instanceof Store && Authorization::canManageStore($actor, $store)) {
                            return;
                        }
                    }
                    $fail(\__('You must select at least one store that you manage.'));
                },
            ],
            'store_ids.*' => 'integer|exists:stores,id',
        ]);

        $storeIds = $validated->array('store_ids');
        $validStoreIds = [];
        foreach ($storeIds as $storeId) {
            $storeIdInt = \is_int($storeId) ? $storeId : (\is_string($storeId) && \ctype_digit($storeId) ? (int) $storeId : 0);
            $store = Store::query()->find($storeIdInt);
            if ($store instanceof Store && Authorization::canManageStore($actor, $store)) {
                $validStoreIds[] = $store->getKey();
            }
        }

        $maxHoursRaw = $validated->mixed('max_hours_per_week');
        $maxHours = null;
        if (\is_int($maxHoursRaw)) {
            $maxHours = $maxHoursRaw;
        } elseif (\is_string($maxHoursRaw) && \ctype_digit($maxHoursRaw)) {
            $maxHours = (int) $maxHoursRaw;
        }

        $isActiveRaw = $validated->mixed('is_active');
        $isActive = \is_bool($isActiveRaw) ? $isActiveRaw : true;

        $employee->forceFill([
            'name' => $validated->assertString('name'),
            'email' => $validated->assertNullableString('email'),
            'phone' => $validated->assertNullableString('phone'),
            'role_label' => $validated->assertNullableString('role_label'),
            'max_hours_per_week' => $maxHours,
            'is_active' => $isActive,
        ])->save();

        // Safe multi-tenant scoping for pivot updates:
        // Detach only stores managed by the manager that are not in the submitted list.
        /** @var array<int, int> $actorStoreIds */
        $actorStoreIds = Authorization::managedStores($actor)->pluck('id')->all();
        $toDetach = \array_diff($actorStoreIds, $validStoreIds);
        if ($toDetach !== []) {
            $employee->stores()->detach($toDetach);
        }

        // Sync without detaching the manageable store IDs.
        $employee->stores()->syncWithoutDetaching($validStoreIds);

        $request->session()->flash('success', \__('Employee updated.'));

        return \redirect('/employees/show?id=' . $employee->getKey());
    }
}
