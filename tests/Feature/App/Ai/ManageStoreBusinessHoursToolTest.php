<?php

declare(strict_types=1);

namespace Tests\Feature\App\Ai;

use App\Ai\Tools\ManageStoreBusinessHoursTool;
use App\Enums\UserRoleEnum;
use App\Models\Store;
use App\Models\StoreBusinessHour;
use App\Models\User;
use Database\Factories\StoreFactory;
use Database\Factories\UserFactory;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('tool description is correct', function (): void {
    $tool = new ManageStoreBusinessHoursTool();
    static::assertStringContainsString('Otevírací doba', $tool->description());
    static::assertStringContainsString('Otváracie hodiny', $tool->description());
});

\test('manager can get business hours for their own store', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $store = \managedStoreFor($manager, 'Owned Store');

    StoreBusinessHour::query()->create([
        'store_id' => $store->getKey(),
        'day_of_week' => 1,
        'opens_at' => '08:00:00',
        'closes_at' => '17:00:00',
        'is_closed' => false,
    ]);

    $this->be($manager, 'users');

    $tool = new ManageStoreBusinessHoursTool();
    $payload = \decodeToolJson($tool->handle(new Request([
        'action' => 'get',
        'store_id' => $store->getKey(),
    ])));

    static::assertSame($store->getKey(), $payload['store_id']);
    static::assertSame('Owned Store', $payload['store_name']);
    static::assertCount(7, $payload['hours']);

    // Monday (day_of_week = 1) details
    $monday = \collect($payload['hours'])->firstWhere('day_of_week', 1);
    static::assertNotNull($monday);
    static::assertSame('08:00', $monday['opens_at']);
    static::assertSame('17:00', $monday['closes_at']);
    static::assertFalse($monday['is_closed']);
});

\test('business hours update is rejected because changes require proposals', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $store = \managedStoreFor($manager, 'Owned Store');

    $this->be($manager, 'users');

    $tool = new ManageStoreBusinessHoursTool();
    $payload = \decodeToolJson($tool->handle(new Request([
        'action' => 'update',
        'store_id' => $store->getKey(),
        'hours' => [
            [
                'day_of_week' => 1, // Monday
                'opens_at' => '09:00',
                'closes_at' => '18:00',
                'is_closed' => false,
            ],
            [
                'day_of_week' => 7, // Sunday
                'opens_at' => null,
                'closes_at' => null,
                'is_closed' => true,
            ],
        ],
    ])));

    static::assertSame('Business hours changes must be created as a pending proposal with ProposeSchedulingChangesTool action business_hours.update.', $payload['error']);
    static::assertFalse(StoreBusinessHour::query()->where('store_id', $store->getKey())->exists());
});

\test('user cannot get or update business hours for a store they do not manage', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $foreignStore = Typer::assertInstance(StoreFactory::new()->createOne(), Store::class);

    $this->be($manager, 'users');

    $tool = new ManageStoreBusinessHoursTool();

    // Get should fail or return error
    $getPayload = \decodeToolJson($tool->handle(new Request([
        'action' => 'get',
        'store_id' => $foreignStore->getKey(),
    ])));
    static::assertArrayHasKey('error', $getPayload);

    // Update should fail or return error
    $updatePayload = \decodeToolJson($tool->handle(new Request([
        'action' => 'update',
        'store_id' => $foreignStore->getKey(),
        'hours' => [
            [
                'day_of_week' => 1,
                'opens_at' => '08:00',
                'closes_at' => '17:00',
                'is_closed' => false,
            ],
        ],
    ])));
    static::assertArrayHasKey('error', $updatePayload);
});

\test('update action consistently instructs proposal use without mutating records', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $store = \managedStoreFor($manager, 'Owned Store');

    $this->be($manager, 'users');
    $tool = new ManageStoreBusinessHoursTool();

    $payload = \decodeToolJson($tool->handle(new Request([
        'action' => 'update',
        'store_id' => $store->getKey(),
        'hours' => [
            [
                'day_of_week' => 1,
                'opens_at' => '17:00',
                'closes_at' => '08:00',
                'is_closed' => false,
            ],
        ],
    ])));
    static::assertSame('Business hours changes must be created as a pending proposal with ProposeSchedulingChangesTool action business_hours.update.', $payload['error']);
    static::assertFalse(StoreBusinessHour::query()->where('store_id', $store->getKey())->exists());
});
