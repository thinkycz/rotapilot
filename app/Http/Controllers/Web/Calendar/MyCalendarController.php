<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Calendar;

use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
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
        $month = (string) $request->query('month', \now()->format('Y-m'));

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

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }

        $storeIds = $profile->stores()->pluck('stores.id')->all();
        if (empty($storeIds)) {
            $storeIds = [0];
        }

        $assignments = ShiftAssignment::query()
            ->getQuery()
            ->where('employee_profile_id', $profile->getKey())
            ->where('status', '!=', 'cancelled')
            ->get();

        $requirementIds = [];
        foreach ($assignments as $a) {
            $requirementIds[] = (int) $a->shift_requirement_id;
        }

        $requirements = [];
        if (!empty($requirementIds)) {
            $reqRows = ShiftRequirement::query()
                ->getQuery()
                ->whereIn('id', $requirementIds)
                ->whereIn('store_id', $storeIds)
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->get();
            foreach ($reqRows as $r) {
                $requirements[(int) $r->id] = $r;
            }
        }

        $scheduleIds = [];
        foreach ($requirements as $r) {
            $scheduleIds[(int) $r->schedule_id] = true;
        }
        $scheduleIds = \array_keys($scheduleIds);

        $publishedSchedules = [];
        if (!empty($scheduleIds)) {
            $schedRows = Schedule::query()
                ->getQuery()
                ->whereIn('id', $scheduleIds)
                ->where('status', 'published')
                ->get();
            foreach ($schedRows as $s) {
                $publishedSchedules[(int) $s->id] = $s;
            }
        }

        $shifts = [];
        foreach ($assignments as $a) {
            $req = $requirements[(int) $a->shift_requirement_id] ?? null;
            if ($req === null) {
                continue;
            }
            $schedule = $publishedSchedules[(int) $req->schedule_id] ?? null;
            if ($schedule === null) {
                continue;
            }

            $storeRow = Store::query()->find((int) $req->store_id);
            $shifts[] = [
                'id' => (int) $a->id,
                'date' => (string) $req->date,
                'start_time' => (string) $req->start_time,
                'end_time' => (string) $req->end_time,
                'role_label' => $req->role_label !== null ? (string) $req->role_label : null,
                'note' => $req->note !== null ? (string) $req->note : null,
                'store_name' => $storeRow instanceof Store ? $storeRow->getName() : '—',
                'schedule_name' => (string) $schedule->name,
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
