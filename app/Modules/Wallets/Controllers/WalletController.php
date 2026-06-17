<?php

namespace App\Modules\Wallets\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallets\Requests\DepositWalletRequest;
use App\Modules\Wallets\Resources\WalletResource;
use App\Modules\Wallets\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        $result = $this->walletService->getWalletSummaryForUser(
            (int) $request->user()->id
        );

        return $this->success(
            'Wallet retrieved successfully',
            $result['data'],
            $this->meta($result['source'], $startedAt)
        );
    }

    public function deposit(DepositWalletRequest $request): JsonResponse
    {
        $wallet = $this->walletService->depositToUserWallet(
            (int) $request->user()->id,
            $request->validated('amount'),
        );

        return $this->success(
            'Wallet deposit completed successfully',
            new WalletResource($wallet)
        );
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    private function success(
        string $message,
        mixed $data,
        ?array $meta = null,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => null,
        ], $status);
    }

    /**
     * @return array{source: string, response_time_ms: float}
     */
    private function meta(string $source, float $startedAt): array
    {
        return [
            'source' => $source,
            'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }
}
