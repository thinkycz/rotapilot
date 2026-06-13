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
        Resolver::resolveDatabaseManager()->connection()->table('shift_assignments')->where('status', 'assigned')->update(['status' => 'confirmed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveDatabaseManager()->connection()->table('shift_assignments')->where('status', 'confirmed')->update(['status' => 'assigned']);
    }
};
