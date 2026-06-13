<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\MyAvailabilities;

use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyAvailabilityIndexController
{
    /**
     * Page size for the index view.
     */
    public const int TAKE = 25;

    /**
     * Show the logged-in employee's availability.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $monthVal = $request->query('month');
        $month = \is_string($monthVal) ? $monthVal : \now()->format('Y-m');
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $days[] = $date->format('Y-m-d');
        }

        $profile = $this->profile($user);
        if (!$profile instanceof EmployeeProfile) {
            return Inertia::render('my-availabilities/Index', [
                'has_profile' => false,
                'month' => $start->format('Y-m'),
                'days' => $days,
                'entries' => [],
            ]);
        }

        $entries = EmployeeAvailability::query()
            ->with('store')
            ->where('employee_profile_id', $profile->getKey())
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn(EmployeeAvailability $entry): array => $this->entry($entry, $user))
            ->values()
            ->all();

        return Inertia::render('my-availabilities/Index', [
            'has_profile' => true,
            'month' => $start->format('Y-m'),
            'days' => $days,
            'entries' => $entries,
        ]);
    }

    /**
     * Get the employee profile for the user.
     */
    private function profile(User $user): EmployeeProfile|null
    {
        return EmployeeProfile::query()->where('user_id', $user->getKey())->first();
    }

    /**
     * Format a single availability entry.
     *
     * @return array<string, mixed>
     */
    private function entry(EmployeeAvailability $entry, User $user): array
    {
        $store = $entry->getStore();

        return [
            'id' => $entry->getKey(),
            'date' => $entry->getDate(),
            'type' => $entry->getType()->value,
            'start_time' => $entry->getStartTime(),
            'end_time' => $entry->getEndTime(),
            'note' => $entry->getNote(),
            'source' => $entry->getSource()->value,
            'store_name' => $store?->getName(),
            'can_edit' => MyAvailabilityWriteController::canEdit($entry, $user),
        ];
    }
}
