<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Exception;

class WalletService
{
    /**
     * Acquire a lock for the user's wallet to prevent concurrent double-spending.
     */
    public function acquireLock($userId, $ttl = 10)
    {
        // Key: wallet:lock:{user_id}
        // SETNX in Redis
        $key = "wallet:lock:{$userId}";
        $isLocked = Redis::setnx($key, 1);

        if ($isLocked) {
            Redis::expire($key, $ttl);
            return $key;
        }

        return false;
    }

    public function releaseLock($key)
    {
        Redis::del($key);
    }

    /**
     * Credit the wallet atomically.
     */
    public function credit(User $user, float $amount, $trxId, $referenceId, $description = '', $meta = [])
    {
        return DB::transaction(function () use ($user, $amount, $trxId, $referenceId, $description, $meta) {
            
            // Lock the row for update to ensure consistency within DB transaction as well
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrCreate([
                'user_id' => $user->id
            ]);

            $wallet->balance += $amount;
            $wallet->save();

            $transaction = $wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'credit',
                'trx_id' => $trxId,
                'reference_id' => $referenceId,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'meta' => $meta,
            ]);

            return $transaction;
        });
    }

    /**
     * Debit the wallet atomically.
     */
    public function debit(User $user, float $amount, $trxId, $referenceId, $description = '', $meta = [])
    {
        return DB::transaction(function () use ($user, $amount, $trxId, $referenceId, $description, $meta) {
            
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $amount) {
                throw new Exception('Insufficient Balance');
            }

            $wallet->balance -= $amount;
            $wallet->save();

            $transaction = $wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'debit',
                'trx_id' => $trxId,
                'reference_id' => $referenceId,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'meta' => $meta,
            ]);

            return $transaction;
        });
    }
}
