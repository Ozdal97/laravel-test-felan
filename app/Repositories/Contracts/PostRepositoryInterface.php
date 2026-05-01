<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PostRepositoryInterface
{
    public function paginateWithAuthor(int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Post;

    public function getPublishedForUser(int $userId): Collection;

    public function create(array $data): Post;

    public function delete(Post $post): bool;
}
