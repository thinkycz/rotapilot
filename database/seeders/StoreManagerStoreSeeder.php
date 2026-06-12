<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Config;

class StoreManagerStoreSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        $manager = User::query()->where('email', 'manager@example.com')->first();
        if (!$manager instanceof User) {
            return;
        }

        $stores = Store::query()->get();

        $manager->managedStores()->syncWithoutDetaching($stores->pluck('id')->all());
    }
}
