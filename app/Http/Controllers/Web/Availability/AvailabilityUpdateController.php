<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\EmployeeAvailabilityValidity;
use App\Models\EmployeeAvailability;
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
        $id = (int) $request->query('id', '0');
        $row = EmployeeAvailability::query()->getQuery()->where('id', $id)->first();
        if ($row === null) {
            \abort(404);
        }

        $validity = EmployeeAvailabilityValidity::inject();
        $validated = $this->validateRequest($request, [
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'type' => 'required|in:available,unavailable,preferred',
            'note' => 'nullable|string|max:2048',
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

        EmployeeAvailability::query()
            ->getQuery()
            ->where('id', $id)
            ->update([
                'type' => $validated->assertString('type'),
                'start_time' => $isClosed ? null : $startStr,
                'end_time' => $isClosed ? null : $endStr,
                'note' => $validated->has('note') ? (\is_string($validated->mixed('note')) ? $validated->mixed('note') : null) : null,
                'updated_at' => \now(),
            ]);

        $request->session()->flash('success', \__('Availability updated.'));

        return \back();
    }
}
