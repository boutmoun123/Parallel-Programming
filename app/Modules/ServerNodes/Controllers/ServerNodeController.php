<?php

namespace App\Modules\ServerNodes\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ServerNode;
use App\Modules\ServerNodes\Requests\StoreServerNodeRequest;
use App\Modules\ServerNodes\Requests\UpdateServerNodeRequest;
use App\Modules\ServerNodes\Resources\ServerNodeResource;
use App\Modules\ServerNodes\Services\ServerNodeService;
use Illuminate\Http\JsonResponse;

class ServerNodeController extends Controller
{
    public function __construct(private readonly ServerNodeService $serverNodeService)
    {
    }

    public function index(): JsonResponse
    {
        $serverNodes = $this->serverNodeService->getLatestServerNodes();

        return $this->success('Server nodes retrieved successfully', ServerNodeResource::collection($serverNodes));
    }

    public function store(StoreServerNodeRequest $request): JsonResponse
    {
        $serverNode = $this->serverNodeService->createServerNode($request->validated());

        return $this->success('Server node created successfully', new ServerNodeResource($serverNode), 201);
    }

    public function show(ServerNode $serverNode): JsonResponse
    {
        return $this->success('Server node retrieved successfully', new ServerNodeResource($serverNode));
    }

    public function update(UpdateServerNodeRequest $request, ServerNode $serverNode): JsonResponse
    {
        $serverNode = $this->serverNodeService->updateServerNode($serverNode, $request->validated());

        return $this->success('Server node updated successfully', new ServerNodeResource($serverNode));
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
