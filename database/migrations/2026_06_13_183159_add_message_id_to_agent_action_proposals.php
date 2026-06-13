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
        Resolver::resolveSchemaBuilder()->table('agent_action_proposals', static function (Blueprint $table): void {
            $table->string('message_id', 36)->nullable()->after('conversation_id');
            $table->index('message_id', 'agent_action_proposals_message_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('agent_action_proposals', static function (Blueprint $table): void {
            $table->dropIndex('agent_action_proposals_message_id_idx');
            $table->dropColumn('message_id');
        });
    }
};
