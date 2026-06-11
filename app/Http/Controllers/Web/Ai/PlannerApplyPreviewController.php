<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Ai;

use App\Enums\ShiftSourceEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\Db;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PlannerApplyPreviewController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(private readonly ConflictDetectionService $conflicts) {}

    /**
     * Apply a preview to the database.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();
        $validated = $this->validateRequest($request, [
            'store_id' => 'required|integer|exists:stores,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'name' => 'required|string|max:255',
            'shift_requirements' => 'required|array',
            'shift_requirements.*.date' => 'required|date',
            'shift_requirements.*.start_time' => 'required|date_format:H:i',
            'shift_requirements.*.end_time' => 'required|date_format:H:i',
            'shift_requirements.*.required_employee_count' => 'required|integer|min:1',
            'shift_requirements.*.role_label' => 'nullable|string',
            'shift_requirements.*.note' => 'nullable|string',
        ]);

        $storeId = (int) $validated->mixed('store_id');
        $store = ModelFinder::findOrAbort(Store::class, $storeId);
        if (!Authorization::canManageStore($user, $store)) {
            \abort(403);
        }

        // Create or find the schedule.
        $schedule = Schedule::query()
            ->getQuery()
            ->where('store_id', $storeId)
            ->where('period_start', (string) $validated->mixed('period_start'))
            ->where('period_end', (string) $validated->mixed('period_end'))
            ->first();
        if ($schedule === null) {
            $schedule = new Schedule();
            $schedule->forceFill([
                'name' => (string) $validated->mixed('name'),
                'store_id' => $storeId,
                'period_start' => (string) $validated->mixed('period_start'),
                'period_end' => (string) $validated->mixed('period_end'),
                'status' => 'draft',
                'created_by' => $user->getKey(),
            ])->save();
        } else {
            $schedule = Db::hydrateOne($schedule, Schedule::class);
        }

        $rows = $validated->array('shift_requirements');

        foreach ($rows as $row) {
            $req = new ShiftRequirement();
            $req->forceFill([
                'schedule_id' => $schedule->getKey(),
                'store_id' => $storeId,
                'date' => (string) ($row['date'] ?? ''),
                'start_time' => (string) ($row['start_time'] ?? ''),
                'end_time' => (string) ($row['end_time'] ?? ''),
                'required_employee_count' => (int) ($row['required_employee_count'] ?? 1),
                'role_label' => isset($row['role_label']) ? (string) $row['role_label'] : null,
                'note' => isset($row['note']) ? (string) $row['note'] : null,
                'source' => ShiftSourceEnum::Ai->value,
                'created_by' => $user->getKey(),
            ])->save();
        }

        $this->conflicts->recompute($schedule);

        $request->session()->flash('success', \__('AI schedule applied.'));

        return \redirect('/schedules/show?id=' . $schedule->getKey());
    }
}
