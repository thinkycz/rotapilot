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
        Resolver::resolveSchemaBuilder()->create('shift_requirements', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('required_employee_count')->default(1);
            $table->string('role_label')->nullable();
            $table->text('note')->nullable();
            $table->string('source')->default('manual');
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
        });
    }
};
