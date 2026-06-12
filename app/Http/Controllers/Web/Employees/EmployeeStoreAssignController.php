<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
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

        $employee = ModelFinder::findOrAbort(EmployeeProfile::class, $employeeId);
        $store = ModelFinder::findOrAbort(Store::class, $storeId);

        if (!Authorization::canManageStore($actor, $store)) {
            \abort(403);
        }

        $employee->stores()->syncWithoutDetaching([$store->getKey()]);

        $request->session()->flash('success', \__('Employee assigned to store.'));

        return \redirect('/employees/show?id=' . $employee->getKey());
    }
}
