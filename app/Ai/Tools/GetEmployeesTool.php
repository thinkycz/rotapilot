<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

class GetEmployeesTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get employees across the manager\'s stores. Use for employees, staff, employee IDs/names, Czech "Zaměstnanci", and Slovak "Zamestnanci".';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'store_id' => $schema->string()
                ->description('Optional store ID to filter employees by.')
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

        $storeIdVal = $request['store_id'] ?? null;
        $storeId = Typer::assertNullableString($storeIdVal);

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

        $employees = EmployeeProfile::query()
            ->whereHas('stores', static fn($q) => $q->whereIn('stores.id', $targetStoreIds))
            ->with('stores')
            ->get();

        return $employees->map(static fn(EmployeeProfile $emp): array => [
            'id' => $emp->getKey(),
            'name' => $emp->getName(),
            'role_label' => $emp->getRoleLabel(),
            'max_hours_per_week' => $emp->getMaxHoursPerWeek(),
            'is_active' => $emp->getIsActive(),
            'stores' => $emp->getStores()->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->all(),
        ])->toJson();
    }
}
