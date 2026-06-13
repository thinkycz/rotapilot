<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

class GetAvailabilityTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get employee availability/unavailability records for a date range. Use for availability, time off, backup coverage, missing availability, Czech "Požadavky"/"Dostupnost"/"Volno", and Slovak "Dostupnosť"/"Voľno".';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'start_date' => $schema->string()
                ->description('Start date of the range (YYYY-MM-DD).')
                ->required(),
            'end_date' => $schema->string()
                ->description('End date of the range (YYYY-MM-DD).')
                ->required(),
            'employee_profile_id' => $schema->string()
                ->description('Optional employee profile ID to filter records by.')
                ->nullable(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $user = User::mustAuth();
        $managedStores = Authorization::managedStores($user);
        $managedStoreIds = $managedStores->pluck('id')->all();

        $managedEmployeeIds = EmployeeProfile::query()
            ->whereHas('stores', static fn($q) => $q->whereIn('stores.id', $managedStoreIds))
            ->pluck('id')
            ->all();

        $startDate = Typer::assertString($request['start_date'] ?? null);
        $endDate = Typer::assertString($request['end_date'] ?? null);
        $employeeProfileIdVal = $request['employee_profile_id'] ?? null;
        $employeeProfileId = Typer::assertNullableString($employeeProfileIdVal);

        if ($employeeProfileId !== null) {
            $empIdInt = (int) $employeeProfileId;
            if (!\in_array($empIdInt, $managedEmployeeIds, true)) {
                $errorJson = \json_encode([
                    'error' => 'You do not have permission to access employee profile ID ' . $employeeProfileId,
                ]);

                return $errorJson === false ? '' : $errorJson;
            }
            $targetEmployeeIds = [$empIdInt];
        } else {
            $targetEmployeeIds = $managedEmployeeIds;
        }

        $records = EmployeeAvailability::query()
            ->whereIn('employee_profile_id', $targetEmployeeIds)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->with('employeeProfile')
            ->get();

        return $records->map(static function (EmployeeAvailability $rec): array {
            $profile = $rec->getEmployeeProfile();

            return [
                'id' => $rec->getKey(),
                'employee' => [
                    'id' => $profile->getKey(),
                    'name' => $profile->getName(),
                ],
                'date' => $rec->getDate(),
                'start_time' => $rec->getStartTime(),
                'end_time' => $rec->getEndTime(),
                'type' => $rec->getType()->value,
                'note' => $rec->getNote(),
            ];
        })->toJson();
    }
}
