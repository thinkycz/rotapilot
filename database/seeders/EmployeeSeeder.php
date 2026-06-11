<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Thinkycz\LaravelCore\Support\Config;

class EmployeeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        $employees = [
            ['email' => 'anna@example.com', 'name' => 'Anna Novak', 'role_label' => 'Barista', 'max_hours_per_week' => 30],
            ['email' => 'pavel@example.com', 'name' => 'Pavel Svoboda', 'role_label' => 'Cashier', 'max_hours_per_week' => 40],
            ['email' => 'lucie@example.com', 'name' => 'Lucie Kralova', 'role_label' => 'Barista', 'max_hours_per_week' => 25],
            ['email' => 'tomas@example.com', 'name' => 'Tomas Dvorak', 'role_label' => 'Shift Lead', 'max_hours_per_week' => 40],
        ];

        foreach ($employees as $row) {
            $userRow = User::query()->getQuery()->where('email', $row['email'])->first();
            $user = $userRow !== null ? \App\Support\Db::hydrateOne($userRow, User::class) : null;
            if (!$user instanceof User) {
                $user = new User();
                $user->forceFill([
                    'email' => $row['email'],
                    'password' => Hash::make('password'),
                    'role' => 'employee',
                    'is_active' => true,
                    'email_verified_at' => \now(),
                    'locale' => 'en',
                ])->save();
            } else {
                $user->forceFill([
                    'role' => 'employee',
                    'is_active' => true,
                ])->save();
            }

            EmployeeProfile::query()->getQuery()->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role_label' => $row['role_label'],
                    'max_hours_per_week' => $row['max_hours_per_week'],
                    'is_active' => true,
                    'updated_at' => \now(),
                    'created_at' => \now(),
                ],
            );
        }
    }
}
