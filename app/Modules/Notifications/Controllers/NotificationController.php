<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Modules\Notifications\Requests\StoreNotificationRequest;
use App\Modules\Notifications\Resources\NotificationResource;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(): JsonResponse
    {
        $notifications = Notification::query()
            ->latest()
            ->get();

        return $this->success('Notifications retrieved successfully', NotificationResource::collection($notifications));
    }

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationService->createNotification($request->validated());

        return $this->success('Notification created successfully', new NotificationResource($notification), 201);
    }

    public function show(Notification $notification): JsonResponse
    {
        return $this->success('Notification retrieved successfully', new NotificationResource($notification));
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
