<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Calendar;

use App\Models\EmployeeProfile;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyCalendarController
{
    /**
     * Show the logged-in employee's own published shifts.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $monthQuery = $request->query('month');
        $month = \is_string($monthQuery) ? $monthQuery : \now()->format('Y-m');

        $user->loadMissing('employeeProfile');
        $profile = $user->employeeProfile;
        if (!$profile instanceof EmployeeProfile) {
            return Inertia::render('calendar/Mine', [
                'shifts' => [],
                'month' => $month,
                'days' => [],
                'has_profile' => false,
            ]);
        }

        $start = Carbon::createFromFormat('Y-m', $month);
        if (!$start instanceof Carbon) {
            $start = Carbon::now()->startOfMonth();
        } else {
            $start = $start->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }

        $storeIds = $profile->stores()->pluck('stores.id')->all();
        if (\count($storeIds) === 0) {
            $storeIds = [0];
        }

        $assignments = ShiftAssignment::query()
            ->with(['shiftRequirement.store', 'shiftRequirement.schedule'])
            ->where('employee_profile_id', $profile->getKey())
            ->where('status', '!=', 'cancelled')
            ->whereHas('shiftRequirement', static function ($q) use ($storeIds, $start, $end): void {
                $q->whereIn('store_id', $storeIds)
                    ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                    ->whereHas('schedule', static function ($sq): void {
                        $sq->where('status', 'published');
                    });
            })
            ->get();

        $shifts = [];
        foreach ($assignments as $a) {
            $req = $a->getShiftRequirement();
            $store = $req->getStore();
            $schedule = $req->getSchedule();

            $shifts[] = [
                'id' => $a->getKey(),
                'date' => $req->getDate(),
                'start_time' => $req->getStartTime(),
                'end_time' => $req->getEndTime(),
                'role_label' => $req->getRoleLabel(),
                'note' => $req->getNote(),
                'store_name' => $store->getName(),
                'schedule_name' => $schedule->getName(),
            ];
        }

        \usort($shifts, static fn(array $a, array $b): int => \strcmp($a['date'] . $a['start_time'], $b['date'] . $b['start_time']));

        return Inertia::render('calendar/Mine', [
            'shifts' => $shifts,
            'month' => $month,
            'days' => $days,
            'has_profile' => true,
        ]);
    }
}
