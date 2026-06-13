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
        Resolver::resolveSchemaBuilder()->table('shift_assignments', static function (Blueprint $table): void {
            // Add individual indexes first so foreign keys are still supported
            $table->index('shift_requirement_id', 'shift_assignments_requirement_id_idx');
            $table->index('employee_profile_id', 'shift_assignments_employee_profile_id_idx');

            $table->dropUnique('shift_assignments_unique');
            $table->time('start_time')->after('employee_profile_id');
            $table->time('end_time')->after('start_time');

            $table->unique(['shift_requirement_id', 'employee_profile_id', 'start_time'], 'shift_assignments_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('shift_assignments', static function (Blueprint $table): void {
            $table->dropUnique('shift_assignments_unique');
            $table->dropColumn(['start_time', 'end_time']);

            $table->unique(['shift_requirement_id', 'employee_profile_id'], 'shift_assignments_unique');

            $table->dropIndex('shift_assignments_requirement_id_idx');
            $table->dropIndex('shift_assignments_employee_profile_id_idx');
        });
    }
};
