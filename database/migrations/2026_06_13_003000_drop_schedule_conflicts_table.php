<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('schedule_conflicts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback possible as the model is deleted.
    }
};
