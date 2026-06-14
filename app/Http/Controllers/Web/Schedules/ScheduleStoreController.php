<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ScheduleValidity;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ScheduleTitle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ScheduleStoreController
{
    use ValidatesWebRequests;

    /**
     * Create a new schedule.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();

        $validity = ScheduleValidity::inject();
        $validated = $this->validateRequest($request, [
            'store_id' => 'required|integer|exists:stores,id',
            'month' => $validity->month()->required()->toArray(),
            'year' => $validity->year()->required()->toArray(),
        ]);

        $store = Store::query()->find($validated->assertInt('store_id'));
        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canManageStore($actor, $store)) {
            \abort(403);
        }

        $month = $validated->assertInt('month');
        $year = $validated->assertInt('year');

        $periodStart = CarbonImmutable::create($year, $month, 1);
        if (!$periodStart instanceof CarbonImmutable) {
            \abort(422);
        }

        // Refuse to create a duplicate schedule for the same store/period.
        // The DB unique index is the durable guarantee; this check gives a
        // friendly 302 to the existing schedule.
        $existing = Schedule::query()
            ->where('store_id', $store->getKey())
            ->where('period_start', $periodStart->startOfMonth()->format('Y-m-d'))
            ->first();
        if ($existing instanceof Schedule) {
            $request->session()->flash('error', \__('A schedule for this period already exists.'));

            return \redirect('/schedules/show?id=' . $existing->getKey());
        }

        $schedule = new Schedule();
        $schedule->forceFill([
            'name' => ScheduleTitle::generate($store, $periodStart),
            'store_id' => $store->getKey(),
            'period_start' => $periodStart->startOfMonth()->format('Y-m-d'),
            'period_end' => $periodStart->endOfMonth()->format('Y-m-d'),
            'status' => 'draft',
            'created_by' => $actor->getKey(),
        ]);

        DB::transaction(static function () use ($schedule, $store, $actor): void {
            $schedule->save();

            $start = CarbonImmutable::parse($schedule->getPeriodStart());
            $end = CarbonImmutable::parse($schedule->getPeriodEnd());

            $rows = [];
            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                $dayOfWeek = $date->dayOfWeekIso; // 1=Monday..7=Sunday
                $bh = $store->findBusinessHourFor($dayOfWeek);
                if ($bh === null || $bh->getIsClosed()) {
                    continue;
                }
                $opensAt = $bh->getOpensAt();
                $closesAt = $bh->getClosesAt();
                if ($opensAt === null || $closesAt === null) {
                    continue;
                }
                // TODO: switch to ShiftSourceEnum::Manual once the enum is final.
                $rows[] = [
                    'schedule_id' => $schedule->getKey(),
                    'store_id' => $store->getKey(),
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $opensAt,
                    'end_time' => $closesAt,
                    'source' => 'manual',
                    'created_by' => $actor->getKey(),
                    'created_at' => \now(),
                    'updated_at' => \now(),
                ];
            }

            if ($rows !== []) {
                ShiftRequirement::query()->getQuery()->insert($rows);
            }
        });

        $request->session()->flash('success', \__('Schedule created.'));

        return \redirect('/schedules/show?id=' . $schedule->getKey());
    }
}
