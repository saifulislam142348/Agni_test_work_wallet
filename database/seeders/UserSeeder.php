<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Primary Test User
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $wallet = $user->wallet()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 500.00,
                'currency' => 'BDT',
                'is_active' => true,
            ]
        );

        // Add dummy transactions for Test User
        for ($i = 0; $i < 15; $i++) {
            $type = rand(0, 1) ? 'credit' : 'debit';
            $amount = rand(50, 1000);
            
            $wallet->transactions()->create([
                'amount' => $amount,
                'type' => $type,
                'trx_id' => 'TRX' . Str::upper(Str::random(8)),
                'reference_id' => 'REF' . Str::upper(Str::random(6)),
                'balance_after' => $wallet->balance, // Ideally calc per tx, but dummy is fine
                'description' => $type === 'credit' ? 'Money Added' : 'Payment for Service',
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }
        
        // 2. Random Users
        User::factory(5)->create()->each(function ($u) {
            $w = $u->wallet()->create([
                'balance' => rand(100, 5000),
                'currency' => 'BDT',
            ]);

            // Add transactions
            for ($j = 0; $j < 5; $j++) {
                $w->transactions()->create([
                    'amount' => rand(100, 500),
                    'type' => 'credit',
                    'trx_id' => 'TRX' . Str::upper(Str::random(8)),
                    'balance_after' => $w->balance,
                    'description' => 'Opening Balance',
                    'created_at' => now()->subDays(rand(1, 10)),
                ]);
            }
        });
    }
}
