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
        Resolver::resolveSchemaBuilder()->create('shift_assignments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_requirement_id')
                ->constrained('shift_requirements')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('employee_profile_id')
                ->constrained('employee_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('source')->default('manual');
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['shift_requirement_id', 'employee_profile_id']);
        });
    }
};
