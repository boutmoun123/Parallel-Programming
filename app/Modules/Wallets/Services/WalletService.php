<?php

namespace App\Modules\Wallets\Services;

use App\Models\Wallet;
use App\Modules\Wallets\Exceptions\InsufficientWalletBalanceException;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWalletForUser(int $userId): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->first();

        if ($wallet) {
            return $wallet;
        }

        return Wallet::create([
            'user_id' => $userId,
            'balance' => 0,
        ]);
    }

    public function chargeUserWallet(int $userId, float|string $amount): Wallet
    {
        $this->simulatePaymentGatewayDelay();

        $wallet = $this->lockedWalletForUser($userId);
        $amount = round((float) $amount, 2);

        if ((float) $wallet->balance < $amount) {
            throw new InsufficientWalletBalanceException(
                'Insufficient wallet balance.',
                ['wallet' => ['Wallet balance is not enough to complete this checkout.']],
            );
        }

        $wallet->balance = round((float) $wallet->balance - $amount, 2);
        $wallet->save();

        return $wallet;
    }

    public function depositToUserWallet(int $userId, float|string $amount): Wallet
    {
        $this->simulatePaymentGatewayDelay();

        return DB::transaction(function () use ($userId, $amount): Wallet {
            $wallet = $this->lockedWalletForUser($userId);
            $wallet->balance = round((float) $wallet->balance + (float) $amount, 2);
            $wallet->save();

            return $wallet;
        });
    }

    private function lockedWalletForUser(int $userId): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            Wallet::create([
                'user_id' => $userId,
                'balance' => 0,
            ]);

            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $wallet;
    }

    private function simulatePaymentGatewayDelay(): void
    {
        $delaySeconds = (int) config('wallets.payment_delay_seconds', 2);

        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }
    }
}
