<?php

namespace App\Modules\Users\Services;

use App\Models\User;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * @param  array{name: string, phone: string, password: string}  $data
     * @return array{access_token: string, token_type: string, user: UserResource}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'user',

        ]);

        return $this->tokenResponse($user);
    }

    /**
     * @param  array{phone: string, password: string}  $credentials
     * @return array{access_token: string, token_type: string, user: UserResource}|null
     */
    public function login(array $credentials): ?array
    {
        $user = User::query()
            ->where('phone', $credentials['phone'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        return $this->tokenResponse($user);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * @return array{access_token: string, token_type: string, user: UserResource}
     */
    private function tokenResponse(User $user): array
    {
        return [
            'access_token' => $user->createToken('api-token')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ];
    }
}
