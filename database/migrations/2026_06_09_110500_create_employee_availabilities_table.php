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
        Resolver::resolveSchemaBuilder()->create('employee_availabilities', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')
                ->constrained('employee_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('type');
            $table->text('note')->nullable();
            $table->string('source')->default('manager');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();
        });
    }
};
