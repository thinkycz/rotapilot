<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetStoresTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get the list of stores managed by this user.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $user = User::mustAuth();
        $stores = Authorization::managedStores($user);

        return $stores->map(static fn(Store $store): array => [
            'id' => $store->getKey(),
            'name' => $store->getName(),
            'address' => $store->getAddress(),
            'city' => $store->getCity(),
        ])->toJson();
    }
}
