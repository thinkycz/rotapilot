<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        $managerRow = User::query()->getQuery()->where('email', 'manager@example.com')->first();
        $manager = $managerRow !== null ? \App\Support\Db::hydrateOne($managerRow, User::class) : null;
        if (!$manager instanceof User) {
            return;
        }

        $stores = Store::query()->getQuery()->get();

        foreach ($stores as $store) {
            DB::table('store_manager_store')->updateOrInsert(
                ['user_id' => $manager->getKey(), 'store_id' => $store->id],
                ['updated_at' => \now(), 'created_at' => \now()],
            );
        }
    }
}
