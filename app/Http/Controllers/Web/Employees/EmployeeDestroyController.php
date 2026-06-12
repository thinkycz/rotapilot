<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EmployeeDestroyController
{
    /**
     * Delete an employee.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $employee = ModelFinder::findOrAbort(EmployeeProfile::class, $id);

        Authorization::mustViewEmployee($user, $employee);

        $employee->delete();
        $request->session()->flash('success', \__('Employee deleted.'));

        return \redirect('/employees/index');
    }
}
