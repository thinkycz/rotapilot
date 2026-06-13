<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AvailabilityDestroyController
{
    use ValidatesWebRequests;

    /**
     * Delete an availability record.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();
        if (!$actor->isStoreManager()) {
            \abort(403);
        }

        $id = (int) $request->query('id', '0');
        $row = EmployeeAvailability::query()->find($id);
        if (!$row instanceof EmployeeAvailability) {
            \abort(404);
        }

        $row->loadMissing('employeeProfile');
        $employee = $row->employeeProfile;
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        Authorization::mustViewEmployee($actor, $employee);

        $row->delete();

        $request->session()->flash('availability_modal_success', \__('Availability removed.'));

        return \back();
    }
}
