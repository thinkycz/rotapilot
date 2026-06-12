<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Thinkycz\LaravelCore\Support\Config;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        $seed = static function (string $email, string $role): User {
            $existing = User::query()
                ->getQuery()
                ->where('email', $email)
                ->first();

            if ($existing instanceof User) {
                return $existing;
            }

            return UserFactory::new()
                ->password('password')
                ->createOne([
                    'email' => $email,
                    'role' => $role,
                    'is_active' => true,
                    'email_verified_at' => \now(),
                ]);
        };

        $seed('manager@example.com', 'store_manager');
        $seed('anna@example.com', 'employee');

        // Touch the password to ensure consistency in case the factory changes.
        User::query()
            ->getQuery()
            ->whereIn('email', ['manager@example.com', 'anna@example.com'])
            ->update(['password' => Hash::make('password')]);
    }
}
