<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentPostRepository implements PostRepositoryInterface
{
    public function paginateWithAuthor(int $perPage = 15): LengthAwarePaginator
    {
        return Post::query()
            ->with(['user:id,name,email'])
            ->withCount('comments')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Post
    {
        return Post::query()
            ->with(['user:id,name', 'comments.user:id,name'])
            ->find($id);
    }

    public function getPublishedForUser(int $userId): Collection
    {
        return Post::query()
            ->where('user_id', $userId)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get();
    }

    public function create(array $data): Post
    {
        return Post::query()->create($data);
    }

    public function delete(Post $post): bool
    {
        return (bool) $post->delete();
    }
}
