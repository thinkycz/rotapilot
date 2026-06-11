<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\EmployeeStore;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EmployeeStoreAssignController
{
    use ValidatesWebRequests;

    /**
     * Assign an employee to a store.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();
        $employeeId = (int) $request->query('employee_id', '0');
        $storeId = (int) $request->query('store_id', '0');

        $employeeRow = EmployeeProfile::query()->getQuery()->where('id', $employeeId)->first();
        if ($employeeRow === null) {
            \abort(404);
        }
        $employee = Db::hydrateOne($employeeRow, EmployeeProfile::class);
        if ($employee === null) {
            \abort(404);
        }

        $store = Store::query()->find($storeId);
        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canManageStore($actor, $store)) {
            \abort(403);
        }

        $exists = EmployeeStore::query()
            ->getQuery()
            ->where('employee_profile_id', $employee->getKey())
            ->where('store_id', $store->getKey())
            ->exists();
        if (!$exists) {
            EmployeeStore::query()->getQuery()->insert([
                'employee_profile_id' => $employee->getKey(),
                'store_id' => $store->getKey(),
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);
        }

        $request->session()->flash('success', \__('Employee assigned to store.'));

        return \redirect('/employees/show?id=' . $employee->getKey());
    }
}
