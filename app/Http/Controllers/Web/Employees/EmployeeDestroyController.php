<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Models\EmployeeProfile;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EmployeeDestroyController
{
    /**
     * Delete an employee.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $row = EmployeeProfile::query()->getQuery()->where('id', $id)->first();
        if ($row === null) {
            \abort(404);
        }
        $employee = Db::hydrateOne($row, EmployeeProfile::class);
        if ($employee === null) {
            \abort(404);
        }

        $employee->delete();
        $request->session()->flash('success', \__('Employee deleted.'));

        return \redirect('/employees/index');
    }
}
