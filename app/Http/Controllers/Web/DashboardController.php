<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ScheduleConflict;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Show the dashboard.
     */
    public function __invoke(): Response
    {
        $user = User::mustAuth();

        if ($user->isEmployee()) {
            return Inertia::render('dashboard/Employee', $this->employeePayload($user));
        }

        return Inertia::render('dashboard/Manager', $this->managerPayload($user));
    }

    /**
     * Build the manager dashboard payload.
     *
     * @return array<string, mixed>
     */
    private function managerPayload(User $user): array
    {
        $managedStores = Authorization::managedStores($user);
        $storeIds = $managedStores->pluck('id')->all();

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $now->copy()->endOfMonth()->format('Y-m-d');
        $today = $now->format('Y-m-d');

        $activeEmployees = EmployeeProfile::query()
            ->getQuery()
            ->where('is_active', true)
            ->whereIn('id', function ($sub) use ($storeIds): void {
                $sub->select('employee_profile_id')
                    ->from('employee_store')
                    ->whereIn('store_id', $storeIds ?: [0]);
            })
            ->count();

        $shiftsThisMonth = ShiftRequirement::query()
            ->getQuery()
            ->whereIn('store_id', $storeIds ?: [0])
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();

        $openConflicts = ScheduleConflict::query()
            ->getQuery()
            ->whereNull('resolved_at')
            ->whereIn('schedule_id', function ($sub) use ($storeIds): void {
                $sub->select('id')->from('schedules')->whereIn('store_id', $storeIds ?: [0]);
            })
            ->count();

        $recentSchedules = Schedule::query()
            ->getQuery()
            ->whereIn('store_id', $storeIds ?: [0])
            ->orderBy('period_start', 'desc')
            ->limit(5)
            ->get();
        $recentScheduleRows = \App\Support\Db::hydrate($recentSchedules, Schedule::class)
            ->map(static fn(Schedule $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
                'status' => $s->getStatus()->value,
                'period_start' => $s->getPeriodStart(),
                'period_end' => $s->getPeriodEnd(),
            ])->values()->all();

        $upcomingShifts = ShiftRequirement::query()
            ->getQuery()
            ->whereIn('store_id', $storeIds ?: [0])
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();
        $upcomingShiftRows = \App\Support\Db::hydrate($upcomingShifts, ShiftRequirement::class)
            ->map(static fn(ShiftRequirement $s): array => [
                'id' => $s->getKey(),
                'date' => $s->getDate(),
                'start_time' => $s->getStartTime(),
                'end_time' => $s->getEndTime(),
                'store_id' => $s->getStoreId(),
            ])->values()->all();

        return [
            'stats' => [
                'managed_stores' => $managedStores->count(),
                'active_employees' => $activeEmployees,
                'shifts_this_month' => $shiftsThisMonth,
                'open_conflicts' => $openConflicts,
            ],
            'stores' => $managedStores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
            'recent_schedules' => $recentScheduleRows,
            'upcoming_shifts' => $upcomingShiftRows,
        ];
    }

    /**
     * Build the employee dashboard payload.
     *
     * @return array<string, mixed>
     */
    private function employeePayload(User $user): array
    {
        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $endOfWeek = $now->copy()->endOfWeek()->format('Y-m-d');

        $user->loadMissing('employeeProfile');
        $profile = $user->employeeProfile;

        $upcomingAssignments = \collect();
        if ($profile instanceof EmployeeProfile) {
            $upcomingAssignments = ShiftAssignment::query()
                ->getQuery()
                ->where('employee_profile_id', $profile->getKey())
                ->whereIn('shift_requirement_id', function ($sub) use ($today, $endOfWeek): void {
                    $sub->select('id')->from('shift_requirements')
                        ->whereBetween('date', [$today, $endOfWeek]);
                })
                ->get();
        }

        $assignments = \App\Support\Db::hydrate($upcomingAssignments, ShiftAssignment::class)
            ->map(static function (ShiftAssignment $a): array {
                $reqRow = ShiftRequirement::query()->getQuery()->where('id', $a->getShiftRequirementId())->first();
                if ($reqRow === null) {
                    return [];
                }
                $req = \App\Support\Db::hydrateOne($reqRow, ShiftRequirement::class);
                if (!$req instanceof ShiftRequirement) {
                    return [];
                }

                return [
                    'id' => $a->getKey(),
                    'date' => $req->getDate(),
                    'start_time' => $req->getStartTime(),
                    'end_time' => $req->getEndTime(),
                    'store_id' => $req->getStoreId(),
                ];
            })->filter()->values()->all();

        $unavailabilities = \collect();
        if ($profile instanceof EmployeeProfile) {
            $rows = EmployeeAvailability::query()
                ->getQuery()
                ->where('employee_profile_id', $profile->getKey())
                ->where('date', '>=', $today)
                ->where('type', 'unavailable')
                ->orderBy('date')
                ->limit(5)
                ->get();
            $unavailabilities = \App\Support\Db::hydrate($rows, EmployeeAvailability::class)
                ->map(static fn(EmployeeAvailability $a): array => [
                    'id' => $a->getKey(),
                    'date' => $a->getDate(),
                    'note' => $a->getNote(),
                ])->values()->all();
        }

        return [
            'stats' => [
                'upcoming_shifts' => \count($assignments),
            ],
            'upcoming_shifts' => $assignments,
            'unavailabilities' => $unavailabilities,
        ];
    }
}
