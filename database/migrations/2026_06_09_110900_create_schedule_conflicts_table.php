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
        Resolver::resolveSchemaBuilder()->create('schedule_conflicts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('shift_requirement_id')
                ->nullable()
                ->constrained('shift_requirements')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('employee_profile_id')
                ->nullable()
                ->constrained('employee_profiles')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('type');
            $table->string('severity');
            $table->text('message');
            $table->text('suggested_fix')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }
};
