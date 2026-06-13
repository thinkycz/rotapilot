<?php

declare(strict_types=1);

use App\Ai\Agents\SchedulingAgent;
use App\Enums\UserRoleEnum;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('scheduling agent instructions include czech project terminology and availability mapping', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
        'locale' => 'cs',
    ]), User::class);

    $this->be($manager, 'users');

    $instructions = (new SchedulingAgent())->instructions();

    static::assertStringContainsString('Czech (Čeština)', $instructions);
    static::assertStringContainsString('Požadavky', $instructions);
    static::assertStringContainsString('means employee availability/unavailability records', $instructions);
    static::assertStringContainsString('Use `GetAvailabilityTool` for Požadavky', $instructions);
});

\test('scheduling agent instructions include slovak and english terminology', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
        'locale' => 'sk',
    ]), User::class);

    $this->be($manager, 'users');

    $instructions = (new SchedulingAgent())->instructions();

    static::assertStringContainsString('Slovak (Slovenčina)', $instructions);
    static::assertStringContainsString('Prevádzky', $instructions);
    static::assertStringContainsString('Dostupnosť', $instructions);
    static::assertStringContainsString('stores, employees, schedules, shifts, assignments, availability', $instructions);
});

\test('scheduling agent instructions route live data and proposals to the correct tools', function (): void {
    $instructions = (new SchedulingAgent())->instructions();

    static::assertStringContainsString('Always use live tools', $instructions);
    static::assertStringContainsString('Use `GetEmployeesTool` before proposing employee-specific changes', $instructions);
    static::assertStringContainsString('Use `GetShiftsTool` for schedules, shifts, staffing, assignments', $instructions);
    static::assertStringContainsString('modify an existing assignment\'s time window', $instructions);
    static::assertStringContainsString('shift.assignment.update', $instructions);
    static::assertStringContainsString('Use `ProposeSchedulingChangesTool` only when the manager asks to create, update, delete, assign, unassign, update assignments, or auto-fill', $instructions);
    static::assertStringContainsString('do not write a fake tool call or JSON payload in the chat', $instructions);
    static::assertStringContainsString('The only valid way to create a pending proposal is to call `ProposeSchedulingChangesTool`', $instructions);
});
