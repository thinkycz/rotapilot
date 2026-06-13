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
        Resolver::resolveSchemaBuilder()->table('shift_requirements', static function (Blueprint $table): void {
            $table->dropColumn('required_employee_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('shift_requirements', static function (Blueprint $table): void {
            $table->unsignedInteger('required_employee_count')->default(1);
        });
    }
};
