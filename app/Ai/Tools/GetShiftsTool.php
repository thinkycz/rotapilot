<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class GetShiftsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get shift requirements and assignments for a date range. Use for schedules, shifts, staffing, assignments, open/unassigned shifts, Czech "Rozvrhy"/"Směny"/"Přiřazení", and Slovak "Plány"/"Zmeny"/"Priradenia".';
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
            'store_id' => $schema->string()
                ->description('Optional store ID to filter shifts by.')
                ->nullable(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $user = User::mustAuth();
            $managedStores = Authorization::managedStores($user);
            $managedStoreIds = $managedStores->pluck('id')->all();

            $startDate = Typer::parseNullableString($request['start_date'] ?? null) ?? '';
            $endDate = Typer::parseNullableString($request['end_date'] ?? null) ?? '';
            $storeIdVal = $request['store_id'] ?? null;
            $storeId = null;
            if (\is_string($storeIdVal)) {
                $storeId = $storeIdVal;
            } elseif (\is_int($storeIdVal)) {
                $storeId = (string) $storeIdVal;
            }

            if ($storeId !== null) {
                $storeIdInt = (int) $storeId;
                if (!\in_array($storeIdInt, $managedStoreIds, true)) {
                    $errorJson = \json_encode([
                        'error' => 'You do not have permission to access store ID ' . $storeId,
                    ]);

                    return $errorJson === false ? '' : $errorJson;
                }
                $targetStoreIds = [$storeIdInt];
            } else {
                $targetStoreIds = $managedStoreIds;
            }

            $shifts = ShiftRequirement::query()
                ->whereIn('store_id', $targetStoreIds)
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->with(['store', 'assignments.employeeProfile'])
                ->get();

            return $shifts->map(static function (ShiftRequirement $shift): array {
                $activeAssignments = $shift->getAssignments()->filter(
                    static fn(ShiftAssignment $a): bool => $a->getStatus() !== ShiftAssignmentStatusEnum::Cancelled,
                );

                if ($activeAssignments->isEmpty()) {
                    $fillStatus = 'unassigned';
                } elseif ($activeAssignments->contains(static fn(ShiftAssignment $a): bool => $a->getStatus() === ShiftAssignmentStatusEnum::Draft)) {
                    $fillStatus = 'draft';
                } else {
                    $fillStatus = 'confirmed';
                }

                $assignedEmployees = $activeAssignments->map(static fn(ShiftAssignment $a): array => [
                    'assignment_id' => $a->getKey(),
                    'id' => $a->getEmployeeProfile()->getKey(),
                    'name' => $a->getEmployeeProfile()->getName(),
                    'start_time' => $a->getStartTime(),
                    'end_time' => $a->getEndTime(),
                    'status' => $a->getStatus()->value,
                ])->values()->all();

                return [
                    'id' => $shift->getKey(),
                    'schedule_id' => $shift->getScheduleId(),
                    'date' => $shift->getDate(),
                    'start_time' => $shift->getStartTime(),
                    'end_time' => $shift->getEndTime(),
                    'store' => [
                        'id' => $shift->getStore()->getKey(),
                        'name' => $shift->getStore()->getName(),
                    ],
                    'role_label' => $shift->getRoleLabel(),
                    'note' => $shift->getNote(),
                    'fill_status' => $fillStatus,
                    'assigned_employees' => $assignedEmployees,
                ];
            })->toJson();
        } catch (Throwable $e) {
            $encoded = \json_encode(['error' => $e->getMessage()]);

            return \is_string($encoded) ? $encoded : '[]';
        }
    }
}
