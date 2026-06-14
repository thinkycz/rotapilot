<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class ManageStoreBusinessHoursTool implements Tool
{
    /**
     * Day of week names mapping.
     */
    private const array DAY_NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get store business (opening) hours. Use for opening/closing hours, business hours, store times, closed days, Czech "Otevírací doba", and Slovak "Otváracie hodiny". To change business hours, call ProposeSchedulingChangesTool with business_hours.update.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('The action to perform. Must be "get". Use ProposeSchedulingChangesTool with business_hours.update for changes.')
                ->required(),
            'store_id' => $schema->integer()
                ->description('The ID of the store.')
                ->required(),
            'hours' => $schema->array()
                ->items($schema->object([
                    'day_of_week' => $schema->integer()
                        ->description('Day of week (1=Monday..7=Sunday).')
                        ->required(),
                    'opens_at' => $schema->string()
                        ->description('Opening time in HH:MM format (e.g. "08:00") or null if closed.')
                        ->nullable(),
                    'closes_at' => $schema->string()
                        ->description('Closing time in HH:MM format (e.g. "17:00") or null if closed.')
                        ->nullable(),
                    'is_closed' => $schema->boolean()
                        ->description('Whether the store is closed on this day.')
                        ->required(),
                ]))
                ->description('Ignored by this read-only tool. Use ProposeSchedulingChangesTool with business_hours.update for changes.')
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
            $action = Typer::mustParseString($request['action'] ?? 'get');
            $storeIdVal = $request['store_id'] ?? null;

            if ($storeIdVal === null) {
                return $this->error('store_id is required');
            }

            $storeId = Typer::mustParseInt($storeIdVal);
            $store = Store::query()->find($storeId);

            if (!$store instanceof Store) {
                return $this->error('Store not found');
            }

            if ($action === 'get') {
                if (!Authorization::canViewStore($user, $store)) {
                    return $this->error('You do not have permission to view this store.');
                }

                return $this->success([
                    'store_id' => $store->getKey(),
                    'store_name' => $store->getName(),
                    'hours' => $this->getStoreHoursArray($store),
                ]);
            }

            if ($action === 'update') {
                if (!Authorization::canManageStore($user, $store)) {
                    return $this->error('You do not have permission to update this store.');
                }

                return $this->error('Business hours changes must be created as a pending proposal with ProposeSchedulingChangesTool action business_hours.update.');
            }

            return $this->error('Unsupported action: ' . $action);
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get the structured business hours array for a store.
     *
     * @return array<array<string, mixed>>
     */
    private function getStoreHoursArray(Store $store): array
    {
        $hours = $store->getBusinessHours();
        $byDay = [];
        foreach ($hours as $h) {
            $byDay[$h->getDayOfWeek()] = [
                'day_of_week' => $h->getDayOfWeek(),
                'day_name' => self::DAY_NAMES[$h->getDayOfWeek()] ?? 'Unknown',
                'opens_at' => $h->getOpensAt() !== null ? \mb_substr($h->getOpensAt(), 0, 5) : null,
                'closes_at' => $h->getClosesAt() !== null ? \mb_substr($h->getClosesAt(), 0, 5) : null,
                'is_closed' => $h->getIsClosed(),
            ];
        }

        for ($d = 1; $d <= 7; ++$d) {
            if (!isset($byDay[$d])) {
                $byDay[$d] = [
                    'day_of_week' => $d,
                    'day_name' => self::DAY_NAMES[$d],
                    'opens_at' => null,
                    'closes_at' => null,
                    'is_closed' => false,
                ];
            }
        }
        \ksort($byDay);

        return \array_values($byDay);
    }

    /**
     * Generate an error response.
     */
    private function error(string $message): string
    {
        $encoded = \json_encode(['error' => $message]);

        return \is_string($encoded) ? $encoded : '{"error":"Unknown error"}';
    }

    /**
     * Generate a success response.
     *
     * @param array<string, mixed> $payload
     */
    private function success(array $payload): string
    {
        $encoded = \json_encode($payload);

        return \is_string($encoded) ? $encoded : '{"error":"Encoding failed"}';
    }
}
