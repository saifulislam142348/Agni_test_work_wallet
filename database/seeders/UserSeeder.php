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

        $wallet = $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'currency' => 'BDT',
                'is_active' => true,
            ]
        );

        // Clear existing transactions for fresh seed
        $wallet->transactions()->delete();

        $runningBalance = 0;
        $transactions = [];

        // Add dummy transactions for Test User (Chronological order)
        for ($i = 0; $i < 15; $i++) {
            $isCredit = rand(0, 1);
            $amount = rand(50, 1000);
            
            if ($isCredit) {
                $runningBalance += $amount;
                $type = 'credit';
                $desc = 'Money Added';
            } else {
                // Ensure we don't go negative for this test
                if ($runningBalance < $amount) {
                    $amount = rand(10, (int)$runningBalance); // Reduce amount to fit
                    if ($amount <= 0) { // If balance is 0 or very low, switch to credit
                         $amount = 500;
                         $runningBalance += $amount;
                         $type = 'credit';
                         $desc = 'Money Added (Auto-recharge)';
                    } else {
                        $runningBalance -= $amount;
                        $type = 'debit';
                        $desc = 'Payment for Service';
                    }
                } else {
                    $runningBalance -= $amount;
                    $type = 'debit';
                    $desc = 'Payment for Service';
                }
            }

            // Create Transaction
            $wallet->transactions()->create([
                'amount' => $amount,
                'type' => $type,
                'trx_id' => 'TRX' . Str::upper(Str::random(10)),
                'reference_id' => 'REF' . Str::upper(Str::random(8)),
                'balance_after' => $runningBalance,
                'description' => $desc,
                'created_at' => now()->subDays(15 - $i), // Oldest first
            ]);
        }
        
        // Update Final Wallet Balance
        $wallet->update(['balance' => $runningBalance]);
        
        // 2. Random Users
        User::factory(5)->create()->each(function ($u) {
            $initialBalance = rand(100, 5000);
            
            $w = $u->wallet()->create([
                'balance' => $initialBalance,
                'currency' => 'BDT',
            ]);

            // Add Opening Balance Transaction
            $w->transactions()->create([
                'amount' => $initialBalance,
                'type' => 'credit',
                'trx_id' => 'TRX' . Str::upper(Str::random(8)),
                'balance_after' => $initialBalance,
                'description' => 'Opening Balance',
                'created_at' => now()->subDays(10),
            ]);
        });
    }
}
