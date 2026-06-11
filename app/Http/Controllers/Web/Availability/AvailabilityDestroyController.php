<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeAvailability;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AvailabilityDestroyController
{
    use ValidatesWebRequests;

    /**
     * Delete an availability record.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $row = EmployeeAvailability::query()->getQuery()->where('id', $id)->first();
        if ($row === null) {
            \abort(404);
        }

        EmployeeAvailability::query()->getQuery()->where('id', $id)->delete();

        $request->session()->flash('success', \__('Availability removed.'));

        return \back();
    }
}
