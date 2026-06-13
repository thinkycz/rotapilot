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
        Resolver::resolveDatabaseManager()->connection()->table('employee_availabilities')->where('type', 'preferred')->update(['type' => 'backup']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveDatabaseManager()->connection()->table('employee_availabilities')->where('type', 'backup')->update(['type' => 'preferred']);
    }
};
