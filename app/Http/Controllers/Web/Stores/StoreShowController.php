<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreShowController
{
    use ValidatesWebRequests;

    /**
     * Show a single store.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');

        $store = Store::query()->find($id);
        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canViewStore($user, $store)) {
            \abort(403);
        }

        $businessHours = $store->getBusinessHours();

        $managerRows = $store->managers()->getQuery()->orderBy('email')->get();
        $managers = Db::hydrate($managerRows, User::class);

        $employeeRows = $store->employees()->getQuery()->orderBy('name')->get();
        $employees = Db::hydrate($employeeRows, EmployeeProfile::class);

        $scheduleRows = $store->schedules()->getQuery()->orderBy('period_start', 'desc')->limit(10)->get();
        $schedules = Db::hydrate($scheduleRows, Schedule::class);

        return Inertia::render('stores/Show', [
            'store' => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'city' => $store->getCity(),
                'timezone' => $store->getTimezone(),
                'is_active' => $store->getIsActive(),
            ],
            'business_hours' => $businessHours->map(static fn($h): array => [
                'day_of_week' => $h->getDayOfWeek(),
                'opens_at' => $h->getOpensAt(),
                'closes_at' => $h->getClosesAt(),
                'is_closed' => $h->getIsClosed(),
            ])->values()->all(),
            'managers' => $managers->map(static fn($m): array => [
                'id' => $m->getKey(),
                'email' => $m->getEmail(),
            ])->values()->all(),
            'employees' => $employees->map(static fn($e): array => [
                'id' => $e->getKey(),
                'name' => $e->getName(),
                'role_label' => $e->getRoleLabel(),
            ])->values()->all(),
            'schedules' => $schedules->map(static fn($s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
                'status' => $s->getStatus()->value,
                'period_start' => $s->getPeriodStart(),
                'period_end' => $s->getPeriodEnd(),
            ])->values()->all(),
        ]);
    }
}
