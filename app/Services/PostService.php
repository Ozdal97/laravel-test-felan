<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PostService
{
    public function __construct(
        private readonly PostRepositoryInterface $posts,
    ) {
    }

    public function feed(int $perPage = 15): LengthAwarePaginator
    {
        return $this->posts->paginateWithAuthor($perPage);
    }

    public function show(int $id): ?Post
    {
        return $this->posts->findById($id);
    }

    public function userPosts(int $userId): Collection
    {
        return $this->posts->getPublishedForUser($userId);
    }

    public function create(array $data): Post
    {
        return $this->posts->create($data);
    }

    public function delete(Post $post): bool
    {
        return $this->posts->delete($post);
    }
}
