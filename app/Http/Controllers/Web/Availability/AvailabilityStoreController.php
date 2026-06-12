<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Enums\AvailabilitySourceEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\EmployeeAvailabilityValidity;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AvailabilityStoreController
{
    use ValidatesWebRequests;

    /**
     * Create a new availability record.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $storeIdRaw = $request->input('store_id');
        if ($request->has('store_id') && \is_scalar($storeIdRaw) && (int) $storeIdRaw === 0) {
            $request->request->remove('store_id');
        }

        $validity = EmployeeAvailabilityValidity::inject();
        $validated = $this->validateRequest($request, [
            'employee_profile_id' => 'required|integer|exists:employee_profiles,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->nullable()->toArray(),
            'end_time' => $validity->endTime()->nullable()->toArray(),
            'type' => $validity->type()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);

        $actor = User::mustAuth();

        $employeeId = $validated->assertInt('employee_profile_id');
        $employee = EmployeeProfile::query()->find($employeeId);
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        Authorization::mustViewEmployee($actor, $employee);

        $storeId = $validated->has('store_id') ? $validated->assertInt('store_id') : 0;
        if ($storeId > 0) {
            $store = Store::query()->find($storeId);
            if (!$store instanceof Store) {
                \abort(404);
            }
            if (!Authorization::canManageStore($actor, $store)) {
                \abort(403);
            }
        }

        $isClosed = $validated->mixed('type') === 'unavailable';
        $startTime = $validated->has('start_time') ? $validated->mixed('start_time') : null;
        $endTime = $validated->has('end_time') ? $validated->mixed('end_time') : null;
        $startStr = \is_string($startTime) ? $startTime : null;
        $endStr = \is_string($endTime) ? $endTime : null;

        if (!$isClosed) {
            if ($startStr === null || $endStr === null) {
                $request->session()->flash('error', \__('Available/preferred days need start and end times.'));

                return \back();
            }

            if ($endStr <= $startStr) {
                $request->session()->flash('error', \__('End time must be after start time.'));

                return \back();
            }
        }

        EmployeeAvailability::query()->create([
            'employee_profile_id' => $employee->getKey(),
            'store_id' => $storeId > 0 ? $storeId : null,
            'date' => $validated->assertString('date'),
            'start_time' => $isClosed ? null : $startStr,
            'end_time' => $isClosed ? null : $endStr,
            'type' => $validated->assertString('type'),
            'note' => $validated->has('note') ? (\is_string($validated->mixed('note')) ? $validated->mixed('note') : null) : null,
            'source' => AvailabilitySourceEnum::Manager->value,
            'created_by' => $actor->getKey(),
        ]);

        $request->session()->flash('success', \__('Availability added.'));

        return \back();
    }
}
