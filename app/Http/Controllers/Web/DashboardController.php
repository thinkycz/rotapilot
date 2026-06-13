<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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
            ->tap(static fn(Builder $query) => EmployeeProfile::scopeActive($query))
            ->whereHas('stores', static function ($q) use ($storeIds): void {
                $q->whereIn('stores.id', \count($storeIds) === 0 ? [0] : $storeIds);
            })
            ->count();

        $shiftsThisMonth = ShiftRequirement::query()
            ->whereIn('store_id', \count($storeIds) === 0 ? [0] : $storeIds)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();

        $recentScheduleRows = Schedule::query()
            ->whereIn('store_id', \count($storeIds) === 0 ? [0] : $storeIds)
            ->orderBy('period_start', 'desc')
            ->limit(5)
            ->get()
            ->map(static fn(Schedule $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
                'status' => $s->getStatus()->value,
                'period_start' => $s->getPeriodStart(),
                'period_end' => $s->getPeriodEnd(),
            ])->values()->all();

        $upcomingShiftRows = ShiftRequirement::query()
            ->with(['store', 'assignments' => static function (Relation $query): void {
                $query->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
                    ->with('employeeProfile');
            }])
            ->whereIn('store_id', \count($storeIds) === 0 ? [0] : $storeIds)
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(static fn(ShiftRequirement $s): array => [
                'id' => $s->getKey(),
                'date' => $s->getDate(),
                'start_time' => $s->getStartTime(),
                'end_time' => $s->getEndTime(),
                'store_id' => $s->getStoreId(),
                'store_name' => $s->getStore()->getName(),
                'role_label' => $s->getRoleLabel(),
                'employees' => $s->assignments->map(static fn(ShiftAssignment $a): array => [
                    'id' => $a->getEmployeeProfile()->getKey(),
                    'name' => $a->getEmployeeProfile()->getName(),
                ])->values()->all(),
            ])->values()->all();

        return [
            'stats' => [
                'managed_stores' => $managedStores->count(),
                'active_employees' => $activeEmployees,
                'shifts_this_month' => $shiftsThisMonth,
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

        $assignments = [];
        $stores = [];
        if ($profile instanceof EmployeeProfile) {
            $stores = $profile->stores()
                ->orderBy('name')
                ->get()
                ->map(static fn(Store $store): array => [
                    'id' => $store->getKey(),
                    'name' => $store->getName(),
                ])
                ->values()
                ->all();

            $assignments = ShiftAssignment::query()
                ->with('shiftRequirement')
                ->where('employee_profile_id', $profile->getKey())
                ->whereHas('shiftRequirement', function ($sub) use ($today, $endOfWeek): void {
                    $sub->whereBetween('date', [$today, $endOfWeek]);
                })
                ->get()
                ->map(static function (ShiftAssignment $a): array {
                    $req = $a->getShiftRequirement();

                    return [
                        'id' => $a->getKey(),
                        'date' => $req->getDate(),
                        'start_time' => $a->getStartTime(),
                        'end_time' => $a->getEndTime(),
                        'store_id' => $req->getStoreId(),
                    ];
                })->values()->all();
        }

        $unavailabilities = [];
        if ($profile instanceof EmployeeProfile) {
            $unavailabilities = EmployeeAvailability::query()
                ->where('employee_profile_id', $profile->getKey())
                ->where('date', '>=', $today)
                ->where('type', 'unavailable')
                ->orderBy('date')
                ->limit(5)
                ->get()
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
            'assigned_stores' => $stores,
        ];
    }
}
