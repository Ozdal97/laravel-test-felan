<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->service->list());
    }

    public function activeWithPosts(): JsonResponse
    {
        return response()->json($this->service->activeWithPosts());
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->service->show($id);

        abort_if($user === null, 404);

        return response()->json($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->register($request->validated());

        return response()->json($user, 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->service->update($user, $request->validated());

        return response()->json($updated);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->service->delete($user);

        return response()->json(null, 204);
    }
}
