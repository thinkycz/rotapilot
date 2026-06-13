<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\MyAvailabilities;

use App\Enums\AvailabilitySourceEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\EmployeeAvailabilityValidity;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MyAvailabilityWriteController
{
    use ValidatesWebRequests;

    /**
     * Determine if the user has permission to edit the given availability row.
     */
    public static function canEdit(EmployeeAvailability $row, User $user): bool
    {
        return $row->getSource() === AvailabilitySourceEnum::Employee &&
            $row->getCreatedBy() === $user->getKey() &&
            $row->getStoreId() === null;
    }

    /**
     * Create an employee-owned global availability row.
     */
    public function store(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();
        $profile = $this->profileOrAbort($user);
        $validity = EmployeeAvailabilityValidity::inject();
        $validated = $this->validateRequest($request, [
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->nullable()->toArray(),
            'end_time' => $validity->endTime()->nullable()->toArray(),
            'type' => $validity->type()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);

        if ($this->duplicateExists($profile, $user, $validated->assertString('date'))) {
            $request->session()->flash('availability_modal_error', \__('You already have availability for this date.'));

            return \back();
        }

        $times = $this->validatedTimes(
            $request,
            $validated->assertString('type'),
            $validated->mixed('start_time'),
            $validated->mixed('end_time'),
        );
        if ($times === null) {
            return \back();
        }
        $note = $validated->mixed('note');

        EmployeeAvailability::query()->create([
            'employee_profile_id' => $profile->getKey(),
            'store_id' => null,
            'date' => $validated->assertString('date'),
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
            'type' => $validated->assertString('type'),
            'note' => $validated->has('note') && \is_string($note) ? $note : null,
            'source' => AvailabilitySourceEnum::Employee->value,
            'created_by' => $user->getKey(),
        ]);

        $request->session()->flash('availability_modal_success', \__('Availability added.'));

        return \back();
    }

    /**
     * Update an employee-owned global availability row.
     */
    public function update(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();
        $row = $this->editableRowOrAbort($request, $user);
        $validity = EmployeeAvailabilityValidity::inject();
        $validated = $this->validateRequest($request, [
            'start_time' => $validity->startTime()->nullable()->toArray(),
            'end_time' => $validity->endTime()->nullable()->toArray(),
            'type' => $validity->type()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);

        $times = $this->validatedTimes(
            $request,
            $validated->assertString('type'),
            $validated->mixed('start_time'),
            $validated->mixed('end_time'),
        );
        if ($times === null) {
            return \back();
        }
        $note = $validated->mixed('note');

        $row->forceFill([
            'type' => $validated->assertString('type'),
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
            'note' => $validated->has('note') && \is_string($note) ? $note : null,
        ])->save();

        $request->session()->flash('availability_modal_success', \__('Availability updated.'));

        return \back();
    }

    /**
     * Delete an employee-owned global availability row.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();
        $row = $this->editableRowOrAbort($request, $user);

        $row->delete();

        $request->session()->flash('availability_modal_success', \__('Availability removed.'));

        return \back();
    }

    /**
     * Get the user's employee profile or abort with 404.
     */
    private function profileOrAbort(User $user): EmployeeProfile
    {
        $profile = EmployeeProfile::query()->where('user_id', $user->getKey())->first();
        if (!$profile instanceof EmployeeProfile) {
            \abort(404);
        }

        return $profile;
    }

    /**
     * Get the requested editable availability row or abort with 404/403.
     */
    private function editableRowOrAbort(Request $request, User $user): EmployeeAvailability
    {
        $profile = $this->profileOrAbort($user);
        $id = (int) $request->query('id', '0');
        $row = EmployeeAvailability::query()->find($id);
        if (!$row instanceof EmployeeAvailability) {
            \abort(404);
        }

        if ($row->getEmployeeProfileId() !== $profile->getKey() || !self::canEdit($row, $user)) {
            \abort(403);
        }

        return $row;
    }

    /**
     * Check if a duplicate global availability entry already exists for the date.
     */
    private function duplicateExists(EmployeeProfile $profile, User $user, string $date): bool
    {
        return EmployeeAvailability::query()
            ->where('employee_profile_id', $profile->getKey())
            ->whereNull('store_id')
            ->where('source', AvailabilitySourceEnum::Employee->value)
            ->where('created_by', $user->getKey())
            ->where('date', $date)
            ->exists();
    }

    /**
     * Validate and normalize start and end times based on the availability type.
     *
     * @return array{start_time: string|null, end_time: string|null}|null
     */
    private function validatedTimes(Request $request, string $type, mixed $startTime, mixed $endTime): array|null
    {
        $isUnavailable = $type === 'unavailable';
        $startStr = \is_string($startTime) ? $startTime : null;
        $endStr = \is_string($endTime) ? $endTime : null;

        if ($isUnavailable) {
            return ['start_time' => null, 'end_time' => null];
        }

        if ($startStr === null || $endStr === null) {
            $request->session()->flash('availability_modal_error', \__('Available/backup days need start and end times.'));

            return null;
        }

        if ($endStr <= $startStr) {
            $request->session()->flash('availability_modal_error', \__('End time must be after start time.'));

            return null;
        }

        return ['start_time' => $startStr, 'end_time' => $endStr];
    }
}
