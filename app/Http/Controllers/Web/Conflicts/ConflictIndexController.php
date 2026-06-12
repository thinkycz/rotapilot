<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Conflicts;

use App\Models\Schedule;
use App\Models\ScheduleConflict;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConflictIndexController
{
    /**
     * Page size for the index view.
     */
    public const int TAKE = 25;

    /**
     * Show the conflicts page.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $scheduleId = (int) $request->query('schedule_id', '0');

        $query = ScheduleConflict::query()
            ->whereNull('resolved_at');

        if ($scheduleId > 0) {
            $query->where('schedule_id', $scheduleId);
        }

        $managedStores = Authorization::managedStores($user);
        $storeIds = $managedStores->pluck('id')->all();

        $query->whereIn('schedule_id', function (\Illuminate\Database\Query\Builder $sub) use ($storeIds): void {
            $sub->select('id')->from('schedules')->whereIn('store_id', \count($storeIds) === 0 ? [0] : $storeIds);
        });

        $conflicts = $query->get();
        $rows = $conflicts->map(static fn(ScheduleConflict $c): array => [
            'id' => $c->getKey(),
            'type' => $c->getType()->value,
            'severity' => $c->getSeverity()->value,
            'message' => $c->getMessage(),
            'suggested_fix' => $c->getSuggestedFix(),
            'shift_requirement_id' => $c->getShiftRequirementId(),
            'employee_profile_id' => $c->getEmployeeProfileId(),
        ])->values()->all();

        $byType = [];
        foreach ($rows as $row) {
            $byType[$row['type']][] = $row;
        }

        $scheduleList = Schedule::query()
            ->whereIn('id', function (\Illuminate\Database\Query\Builder $sub) use ($storeIds): void {
                $sub->select('id')->from('schedules')->whereIn('store_id', \count($storeIds) === 0 ? [0] : $storeIds);
            })
            ->orderBy('period_start', 'desc')
            ->limit(20)
            ->get()
            ->map(static fn(Schedule $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all();

        return Inertia::render('conflicts/Index', [
            'conflicts' => $rows,
            'by_type' => $byType,
            'schedules' => $scheduleList,
            'schedule_id' => $scheduleId,
        ]);
    }
}
