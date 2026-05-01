<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->service->feed());
    }

    public function show(int $id): JsonResponse
    {
        $post = $this->service->show($id);

        abort_if($post === null, 404);

        return response()->json($post);
    }

    public function byUser(int $userId): JsonResponse
    {
        return response()->json($this->service->userPosts($userId));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->service->create($request->validated());

        return response()->json($post, 201);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->service->delete($post);

        return response()->json(null, 204);
    }
}
