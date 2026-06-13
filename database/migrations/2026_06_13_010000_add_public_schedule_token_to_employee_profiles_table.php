<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('employee_profiles', static function (Blueprint $table): void {
            $table->string('public_schedule_token', 64)->nullable()->unique()->after('is_active');
        });

        $ids = DB::table('employee_profiles')->pluck('id')->all();
        foreach ($ids as $id) {
            if (!\is_int($id) && !(\is_string($id) && \ctype_digit($id))) {
                continue;
            }

            do {
                $token = Str::random(48);
            } while (DB::table('employee_profiles')->where('public_schedule_token', $token)->exists());

            DB::table('employee_profiles')
                ->where('id', (int) $id)
                ->update(['public_schedule_token' => $token]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('employee_profiles', static function (Blueprint $table): void {
            $table->dropUnique('employee_profiles_public_schedule_token_unique');
            $table->dropColumn('public_schedule_token');
        });
    }
};
