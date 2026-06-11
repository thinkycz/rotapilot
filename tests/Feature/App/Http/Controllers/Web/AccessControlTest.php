<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin can view stores index', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);

    $response = $this->be($user, 'users')->get('/stores/index', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Index');
});

\test('store manager not assigned cannot view foreign store', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $storeRow = Store::query()->first();
    $store = $storeRow !== null ? App\Support\Db::hydrateOne($storeRow, Store::class) : null;
    if (!$store instanceof Store) {
        $this->markTestSkipped('No seeded store');
    }

    $response = $this->be($manager, 'users')->get('/stores/show?id=' . $store->getKey());

    $response->assertForbidden();
});

\test('store manager assigned to a store can view it', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'store_manager']), User::class);
    $storeRow = Store::query()->first();
    $store = $storeRow !== null ? App\Support\Db::hydrateOne($storeRow, Store::class) : null;
    if (!$store instanceof Store) {
        $this->markTestSkipped('No seeded store');
    }
    DB::table('store_manager_store')->insert([
        'user_id' => $manager->getKey(),
        'store_id' => $store->getKey(),
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $response = $this->be($manager, 'users')->get('/stores/show?id=' . $store->getKey(), $this->inertiaHeaders());

    $response->assertOk();
});

\test('admin can view schedules index', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);

    $response = $this->be($user, 'users')->get('/schedules/index', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'schedules/Index');
});

\test('admin can view my calendar', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);

    $response = $this->be($user, 'users')->get('/my-calendar', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'calendar/Mine');
});

\test('admin can view conflicts page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);

    $response = $this->be($user, 'users')->get('/conflicts', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'conflicts/Index');
});

\test('admin can view ai planner', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['role' => 'admin']), User::class);

    $response = $this->be($user, 'users')->get('/ai-planner', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'ai/Planner');
});
