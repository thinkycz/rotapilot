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
        Resolver::resolveSchemaBuilder()->table('employee_profiles', static function (Blueprint $table): void {
            $table->unsignedInteger('hourly_rate')->nullable()->after('max_hours_per_week');
        });
    }
};
