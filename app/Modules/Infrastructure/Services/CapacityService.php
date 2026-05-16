<?php

namespace App\Modules\Infrastructure\Services;

use App\Modules\Infrastructure\Data\CapacityReservation;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CapacityService
{
    public function acquire(string $group): ?CapacityReservation
    {
        $settings = $this->settings($group);

        return $this->cache()->lock($this->lockKey($group), $settings['lock_seconds'])
            ->block($settings['wait_seconds'], function () use ($group, $settings): ?CapacityReservation {
                $state = $this->loadState($group, $settings);
                $activeCount = count($state['reservations']);

                if ($activeCount >= $settings['limit']) {
                    return null;
                }

                $token = (string) Str::uuid();
                $state['reservations'][$token] = now()->addSeconds($settings['reservation_ttl_seconds'])->getTimestamp();
                $this->persistState($group, $state, $settings);

                return new CapacityReservation(
                    $group,
                    $token,
                    $settings['limit'],
                    count($state['reservations']),
                    $settings['retry_after_seconds'],
                );
            });
    }

    public function release(CapacityReservation $reservation): void
    {
        $settings = $this->settings($reservation->group);

        $this->cache()->lock($this->lockKey($reservation->group), $settings['lock_seconds'])
            ->block($settings['wait_seconds'], function () use ($reservation, $settings): void {
                $state = $this->loadState($reservation->group, $settings);

                unset($state['reservations'][$reservation->token]);

                $this->persistState($reservation->group, $state, $settings);
            });
    }

    /**
     * @return array{group: string, limit: int, active: int, remaining: int, retry_after_seconds: int}
     */
    public function snapshot(string $group): array
    {
        $settings = $this->settings($group);

        return $this->cache()->lock($this->lockKey($group), $settings['lock_seconds'])
            ->block($settings['wait_seconds'], function () use ($group, $settings): array {
                $state = $this->loadState($group, $settings);
                $activeCount = count($state['reservations']);

                return [
                    'group' => $group,
                    'limit' => $settings['limit'],
                    'active' => $activeCount,
                    'remaining' => max(0, $settings['limit'] - $activeCount),
                    'retry_after_seconds' => $settings['retry_after_seconds'],
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{reservations: array<string, int>}
     */
    private function loadState(string $group, array $settings): array
    {
        $state = $this->cache()->get($this->stateKey($group), [
            'reservations' => [],
        ]);

        $reservations = $state['reservations'] ?? [];
        $now = now()->getTimestamp();

        $state['reservations'] = array_filter(
            $reservations,
            static fn (mixed $expiresAt): bool => is_int($expiresAt) && $expiresAt > $now,
        );

        return $state;
    }

    /**
     * @param  array{reservations: array<string, int>}  $state
     * @param  array<string, mixed>  $settings
     */
    private function persistState(string $group, array $state, array $settings): void
    {
        if ($state['reservations'] === []) {
            $this->cache()->forget($this->stateKey($group));

            return;
        }

        $this->cache()->put(
            $this->stateKey($group),
            $state,
            now()->addSeconds($settings['state_ttl_seconds']),
        );
    }

    /**
     * @return array{
     *     limit: int,
     *     retry_after_seconds: int,
     *     reservation_ttl_seconds: int,
     *     state_ttl_seconds: int,
     *     lock_seconds: int,
     *     wait_seconds: int
     * }
     */
    private function settings(string $group): array
    {
        $groupSettings = config("capacity.groups.{$group}", []);
        $reservationTtl = (int) ($groupSettings['reservation_ttl_seconds'] ?? config('capacity.reservation_ttl_seconds', 120));

        return [
            'limit' => (int) ($groupSettings['limit'] ?? config('capacity.default_limit', 10)),
            'retry_after_seconds' => (int) ($groupSettings['retry_after_seconds'] ?? config('capacity.retry_after_seconds', 2)),
            'reservation_ttl_seconds' => $reservationTtl,
            'state_ttl_seconds' => max($reservationTtl * 2, 60),
            'lock_seconds' => (int) config('capacity.lock_seconds', 5),
            'wait_seconds' => (int) config('capacity.wait_seconds', 2),
        ];
    }

    private function stateKey(string $group): string
    {
        return "capacity:{$group}:state";
    }

    private function lockKey(string $group): string
    {
        return "capacity:{$group}:lock";
    }

    private function cache(): Repository
    {
        return Cache::store(config('capacity.store', config('cache.default')));
    }
}
