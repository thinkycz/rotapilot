<?php

declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\AgentActionProposal;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('getMessageId returns null when the message_id column is not loaded', function (): void {
    $proposal = new AgentActionProposal();

    static::assertNull($proposal->getMessageId());
});

\test('getMessageId returns the stored id when message_id is loaded', function (): void {
    $manager = Typer::assertInstance(UserFactory::new()->createOne([
        'role' => UserRoleEnum::StoreManager->value,
    ]), User::class);
    $conversation = \createAgentConversation($manager);

    $proposal = \createAgentProposal($manager, $conversation->getKey(), []);

    static::assertNull($proposal->getMessageId());

    $messageId = (string) Illuminate\Support\Str::uuid();
    $proposal->forceFill(['message_id' => $messageId])->save();

    static::assertSame($messageId, $proposal->refresh()->getMessageId());
});
