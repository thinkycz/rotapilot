<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('agent_action_proposals', static function (Blueprint $table): void {
            $table->id();
            $table->string('conversation_id');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('summary', 500);
            $table->json('actions');
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'user_id', 'status'], 'agent_action_proposals_conversation_idx');
        });
    }
};
