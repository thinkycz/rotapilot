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
        Resolver::resolveSchemaBuilder()->create('employee_store', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')
                ->constrained('employee_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_profile_id', 'store_id']);
        });
    }
};
