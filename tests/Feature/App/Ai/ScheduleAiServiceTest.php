<?php

declare(strict_types=1);

use App\Ai\Agents\FakeSchedulePlannerAgent;
use App\Models\Store;
use App\Services\Ai\ScheduleAiService;
use Laravel\Ai\Responses\AgentResponse;

\test('fake agent returns deterministic schedule', function (): void {
    $agent = new FakeSchedulePlannerAgent('Downtown Cafe', '2026-06-15', '2026-06-21');

    \expect($agent)->toBeInstanceOf(FakeSchedulePlannerAgent::class);

    $response = $agent->prompt('Anything');

    \expect($response)->toBeInstanceOf(AgentResponse::class);

    $payload = \json_decode((string) $response->text, true);
    \expect($payload)->toBeArray();
    \expect($payload['intent'])->toBe('create_or_update_schedule');
    \expect($payload['shift_requirements'])->toBeArray();
    \expect(\count($payload['shift_requirements']))->toBeGreaterThan(0);
});

\test('schedule ai service falls back to fake agent when no provider', function (): void {
    \expect(ScheduleAiService::hasProvider())->toBeFalse();

    $store = App\Support\Db::hydrateOne(Store::query()->first(), Store::class);
    if (!$store instanceof Store) {
        $this->markTestSkipped('No seeded store');
    }

    $employees = App\Support\Db::hydrate(
        App\Models\EmployeeProfile::query()->get()->all(),
        App\Models\EmployeeProfile::class,
    );

    $result = (new ScheduleAiService())->generate(
        $store,
        Carbon\Carbon::parse('2026-06-15'),
        Carbon\Carbon::parse('2026-06-21'),
        $employees,
        'Plan shifts.',
    );

    \expect($result['intent'])->toBe('create_or_update_schedule');
    \expect($result['shift_requirements'])->toBeArray();
    \expect(\count($result['shift_requirements']))->toBeGreaterThan(0);
});

\test('schedule ai service flags unknown names mentioned in the prompt', function (): void {
    $store = App\Support\Db::hydrateOne(Store::query()->first(), Store::class);
    if (!$store instanceof Store) {
        $this->markTestSkipped('No seeded store');
    }

    $employees = App\Support\Db::hydrate(
        App\Models\EmployeeProfile::query()->get()->all(),
        App\Models\EmployeeProfile::class,
    );

    $result = (new ScheduleAiService())->generate(
        $store,
        Carbon\Carbon::parse('2026-06-15'),
        Carbon\Carbon::parse('2026-06-21'),
        $employees,
        'Plan shifts. Mentioned: Zaphod.',
    );

    \expect($result['warnings'])->toContain('Zaphod was mentioned, but no employee named Zaphod exists.');
});
