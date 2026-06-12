<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
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

        $managers = $store->managers()->orderBy('email')->get();
        $employees = $store->employees()->orderBy('name')->get();
        $schedules = $store->schedules()->orderBy('period_start', 'desc')->limit(10)->get();

        return Inertia::render('stores/Show', [
            'store' => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'city' => $store->getCity(),
                'timezone' => $store->getTimezone(),
                'is_active' => $store->getIsActive(),
            ],
            'business_hours' => self::businessHoursRows($store),
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

    /**
     * Get a complete Monday-first business-hours payload.
     *
     * @return list<array{day_of_week: int, opens_at: string|null, closes_at: string|null, is_closed: bool}>
     */
    private static function businessHoursRows(Store $store): array
    {
        $byDay = [];
        foreach ($store->getBusinessHours() as $hour) {
            $byDay[$hour->getDayOfWeek()] = [
                'day_of_week' => $hour->getDayOfWeek(),
                'opens_at' => $hour->getOpensAt(),
                'closes_at' => $hour->getClosesAt(),
                'is_closed' => $hour->getIsClosed(),
            ];
        }

        $rows = [];
        for ($day = 1; $day <= 7; ++$day) {
            $rows[] = $byDay[$day] ?? [
                'day_of_week' => $day,
                'opens_at' => null,
                'closes_at' => null,
                'is_closed' => false,
            ];
        }

        return $rows;
    }
}
