<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Ai;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\ScheduleAiService;
use App\Support\Authorization;
use App\Support\Db;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PlannerMessageController
{
    use ValidatesWebRequests;

    /**
     * Send a prompt to the AI planner and flash the preview onto the
     * planner page.
     */
    public function __invoke(Request $request, ScheduleAiService $ai): SymfonyResponse
    {
        $user = User::mustAuth();
        $validated = $this->validateRequest($request, [
            'store_id' => 'required|integer|exists:stores,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'name' => 'required|string|max:255',
            'prompt' => 'required|string|max:4000',
        ]);

        $storeId = (int) $validated->mixed('store_id');
        $storeRow = Store::query()->getQuery()->getQuery()->where('id', $storeId)->first();
        $store = $storeRow !== null ? Db::hydrateOne($storeRow, Store::class) : null;
        if (!$store instanceof Store) {
            \abort(404);
        }
        if (!Authorization::canManageStore($user, $store)) {
            \abort(403);
        }

        $periodStart = Carbon::createFromFormat('Y-m-d', (string) $validated->mixed('period_start'));
        $periodEnd = Carbon::createFromFormat('Y-m-d', (string) $validated->mixed('period_end'));

        $employeeRows = EmployeeProfile::query()
            ->getQuery()
            ->getQuery()
            ->whereIn('id', function ($sub) use ($storeId): void {
                $sub->select('employee_profile_id')
                    ->from('employee_store')
                    ->where('store_id', $storeId);
            })
            ->where('is_active', true)
            ->get();

        $employeeCollection = Db::hydrate($employeeRows, EmployeeProfile::class);

        $result = $ai->generate(
            $store,
            $periodStart,
            $periodEnd,
            $employeeCollection,
            (string) $validated->mixed('prompt'),
        );

        $request->session()->flash('ai_preview', $result);

        return \redirect('/ai-planner?store_id=' . $storeId .
            '&period_start=' . $periodStart->format('Y-m-d') .
            '&period_end=' . $periodEnd->format('Y-m-d') .
            '&name=' . \urlencode((string) $validated->mixed('name')));
    }
}
