<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\EmployeeProfileValidity;
use App\Models\EmployeeProfile;
use App\Models\EmployeeStore;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EmployeeStoreController
{
    use ValidatesWebRequests;

    /**
     * Create a new employee.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();
        $validity = EmployeeProfileValidity::inject();

        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'email' => $validity->email()->nullable()->toArray(),
            'phone' => $validity->phone()->nullable()->toArray(),
            'role_label' => $validity->roleLabel()->nullable()->toArray(),
            'max_hours_per_week' => $validity->maxHoursPerWeek()->nullable()->toArray(),
            'is_active' => $validity->isActive()->nullable()->toArray(),
            'store_ids' => 'array',
            'store_ids.*' => 'integer|exists:stores,id',
        ]);

        $maxHoursRaw = $validated->mixed('max_hours_per_week');
        $maxHours = null;
        if (\is_int($maxHoursRaw)) {
            $maxHours = $maxHoursRaw;
        } elseif (\is_string($maxHoursRaw) && \ctype_digit($maxHoursRaw)) {
            $maxHours = (int) $maxHoursRaw;
        }

        $isActiveRaw = $validated->mixed('is_active');
        $isActive = \is_bool($isActiveRaw) ? $isActiveRaw : true;

        $employee = new EmployeeProfile();
        $employee->forceFill([
            'name' => $validated->assertString('name'),
            'email' => $validated->assertNullableString('email'),
            'phone' => $validated->assertNullableString('phone'),
            'role_label' => $validated->assertNullableString('role_label'),
            'max_hours_per_week' => $maxHours,
            'is_active' => $isActive,
        ])->save();

        $storeIds = $validated->array('store_ids');
        foreach ($storeIds as $storeId) {
            $storeIdInt = \is_int($storeId) ? $storeId : (\is_string($storeId) && \ctype_digit($storeId) ? (int) $storeId : 0);
            $store = Store::query()->find($storeIdInt);
            if (!$store instanceof Store) {
                continue;
            }
            if (!Authorization::canManageStore($actor, $store)) {
                continue;
            }
            EmployeeStore::query()->getQuery()->insert([
                'employee_profile_id' => $employee->getKey(),
                'store_id' => $store->getKey(),
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);
        }

        $request->session()->flash('success', \__('Employee created.'));

        return \redirect('/employees/show?id=' . $employee->getKey());
    }
}
