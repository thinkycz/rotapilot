<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\EmployeeAvailabilityValidity;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AvailabilityUpdateController
{
    use ValidatesWebRequests;

    /**
     * Update an availability record.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();
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

        $validity = EmployeeAvailabilityValidity::inject();
        $validated = $this->validateRequest($request, [
            'start_time' => $validity->startTime()->nullable()->toArray(),
            'end_time' => $validity->endTime()->nullable()->toArray(),
            'type' => $validity->type()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);

        $isClosed = $validated->mixed('type') === 'unavailable';
        $startTime = $validated->has('start_time') ? $validated->mixed('start_time') : null;
        $endTime = $validated->has('end_time') ? $validated->mixed('end_time') : null;
        $startStr = \is_string($startTime) ? $startTime : null;
        $endStr = \is_string($endTime) ? $endTime : null;

        if (!$isClosed && ($startStr === null || $endStr === null)) {
            $request->session()->flash('error', \__('Available/preferred days need start and end times.'));

            return \back();
        }

        $row->forceFill([
            'type' => $validated->assertString('type'),
            'start_time' => $isClosed ? null : $startStr,
            'end_time' => $isClosed ? null : $endStr,
            'note' => $validated->has('note') ? (\is_string($validated->mixed('note')) ? $validated->mixed('note') : null) : null,
        ])->save();

        $request->session()->flash('success', \__('Availability updated.'));

        return \back();
    }
}
