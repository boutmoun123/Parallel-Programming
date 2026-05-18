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
        $wallet = $this->walletService->getOrCreateWalletForUser($request->user()->id);

        return $this->success('Wallet retrieved successfully', new WalletResource($wallet));
    }

    public function deposit(DepositWalletRequest $request): JsonResponse
    {
        $wallet = $this->walletService->depositToUserWallet(
            $request->user()->id,
            $request->validated('amount'),
        );

        return $this->success('Wallet deposit completed successfully', new WalletResource($wallet));
    }

    private function success(string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }
}
