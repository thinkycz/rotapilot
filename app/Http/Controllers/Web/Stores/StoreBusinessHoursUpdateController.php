<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Store;
use App\Models\StoreBusinessHour;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StoreBusinessHoursUpdateController
{
    use ValidatesWebRequests;

    /**
     * Update business hours for a store.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $store = Store::query()->find($id);
        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canManageStore($user, $store)) {
            \abort(403);
        }

        $validated = $this->validateRequest($request, [
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|integer|min:1|max:7',
            'hours.*.opens_at' => 'nullable|date_format:H:i',
            'hours.*.closes_at' => 'nullable|date_format:H:i',
            'hours.*.is_closed' => 'required|boolean',
        ]);

        $rows = $validated->array('hours');

        foreach ($rows as $rawRow) {
            $row = (array) $rawRow;
            $dayRaw = $row['day_of_week'] ?? null;
            $day = \is_int($dayRaw) ? $dayRaw : (\is_string($dayRaw) && \ctype_digit($dayRaw) ? (int) $dayRaw : 0);
            $isClosed = (bool) ($row['is_closed'] ?? false);
            $opensRaw = $row['opens_at'] ?? null;
            $closesRaw = $row['closes_at'] ?? null;
            $opens = $isClosed ? null : (\is_string($opensRaw) ? $opensRaw : null);
            $closes = $isClosed ? null : (\is_string($closesRaw) ? $closesRaw : null);

            if (!$isClosed) {
                if ($opens === null || $closes === null) {
                    $request->session()->flash('error', \__('Open days need opening and closing times.'));

                    return \back();
                }

                if ($closes <= $opens) {
                    $request->session()->flash('error', \__('Closing time must be after opening time.'));

                    return \back();
                }
            }

            StoreBusinessHour::query()->updateOrCreate(
                ['store_id' => $store->getKey(), 'day_of_week' => $day],
                [
                    'opens_at' => $opens,
                    'closes_at' => $closes,
                    'is_closed' => $isClosed,
                ],
            );
        }

        $request->session()->flash('success', \__('Business hours updated.'));

        return \redirect('/stores/show?id=' . $store->getKey());
    }
}
