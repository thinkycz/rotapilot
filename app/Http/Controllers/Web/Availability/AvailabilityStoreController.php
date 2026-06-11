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
        $validity = EmployeeAvailabilityValidity::inject();
        $validated = $this->validateRequest($request, [
            'employee_profile_id' => 'required|integer|exists:employee_profiles,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'type' => 'required|in:available,unavailable,preferred',
            'note' => 'nullable|string|max:2048',
        ]);

        $employee = EmployeeProfile::query()->find((int) $validated->mixed('employee_profile_id'));
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        $storeId = $validated->has('store_id') ? (int) $validated->mixed('store_id') : 0;
        if ($storeId > 0) {
            $store = Store::query()->find($storeId);
            if (!$store instanceof Store) {
                \abort(404);
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

        $actor = User::mustAuth();

        EmployeeAvailability::query()->getQuery()->insert([
            'employee_profile_id' => $employee->getKey(),
            'store_id' => $storeId > 0 ? $storeId : null,
            'date' => $validated->assertString('date'),
            'start_time' => $isClosed ? null : $startStr,
            'end_time' => $isClosed ? null : $endStr,
            'type' => $validated->assertString('type'),
            'note' => $validated->has('note') ? (\is_string($validated->mixed('note')) ? $validated->mixed('note') : null) : null,
            'source' => AvailabilitySourceEnum::Manager->value,
            'created_by' => $actor->getKey(),
            'created_at' => \now(),
            'updated_at' => \now(),
        ]);

        $request->session()->flash('success', \__('Availability added.'));

        return \back();
    }
}
