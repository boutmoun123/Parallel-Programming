<?php

namespace App\Modules\Wallets\Services;

use App\Models\Wallet;
use App\Modules\Wallets\Exceptions\InsufficientWalletBalanceException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalletService
{
    private const CACHE_TTL_SECONDS = 60;

    public function getOrCreateWalletForUser(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0]
        );
    }

    /**
     * @return array{data: array<string, mixed>, source: string}
     */
    public function getWalletSummaryForUser(int $userId): array
    {
        $cache = Cache::store(config('cache.default'));
        $cacheKey = $this->walletCacheKey($userId);

        if ($cache->has($cacheKey)) {
            return [
                'data' => $cache->get($cacheKey),
                'source' => 'cache',
            ];
        }

        $wallet = $this->getOrCreateWalletForUser($userId);

        $data = [
            'id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'balance' => $wallet->balance,
            'created_at' => $wallet->created_at?->toDateTimeString(),
            'updated_at' => $wallet->updated_at?->toDateTimeString(),
        ];

        $cache->put($cacheKey, $data, self::CACHE_TTL_SECONDS);

        return [
            'data' => $data,
            'source' => 'database',
        ];
    }

    public function chargeUserWallet(int $userId, float|string $amount): Wallet
    {
        $this->simulatePaymentGatewayDelay();

        return $this->withUserWalletDistributedLock($userId, function () use ($userId, $amount): Wallet {
            return DB::transaction(function () use ($userId, $amount): Wallet {
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

                $this->forgetWalletCache($userId);

                return $wallet->fresh();
            });
        });
    }

    public function depositToUserWallet(int $userId, float|string $amount): Wallet
    {
        $this->simulatePaymentGatewayDelay();

        return $this->withUserWalletDistributedLock($userId, function () use ($userId, $amount): Wallet {
            return DB::transaction(function () use ($userId, $amount): Wallet {
                $wallet = $this->lockedWalletForUser($userId);

                $wallet->balance = round((float) $wallet->balance + (float) $amount, 2);
                $wallet->save();

                $this->forgetWalletCache($userId);

                return $wallet->fresh();
            });
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

    /**
     * Redis/distributed lock: protects wallet balance across more than one PHP process/server.
     * This is intentionally different from the database row lock used inside the transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withUserWalletDistributedLock(int $userId, callable $callback): mixed
    {
        return Cache::store(config('distributed_locks.store', config('cache.default')))
            ->lock(
                "locks:wallet:user:{$userId}",
                (int) config('distributed_locks.default_seconds', 30),
            )
            ->block(
                (int) config('distributed_locks.default_wait_seconds', 10),
                $callback,
            );
    }

    private function forgetWalletCache(int $userId): void
    {
        Cache::store(config('cache.default'))->forget($this->walletCacheKey($userId));
    }

    private function walletCacheKey(int $userId): string
    {
        return "wallets:user:{$userId}:summary";
    }

    private function simulatePaymentGatewayDelay(): void
    {
        $delaySeconds = (int) config('wallets.payment_delay_seconds', 0);

        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }
    }
}
