<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->paginate($perPage);
    }

    public function show(int $id): ?User
    {
        return $this->users->findById($id);
    }

    public function activeWithPosts(): Collection
    {
        return $this->users->getActiveWithPosts();
    }

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);

            return $this->users->create($data);
        });
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->users->update($user, $data);
    }

    public function delete(User $user): bool
    {
        return $this->users->delete($user);
    }

    public function dashboardStats(): array
    {
        $users = User::all();

        $stats = [];
        foreach ($users as $user) {
            $stats[] = [
                'id' => $user->id,
                'name' => $user->name,
                'post_count' => $user->posts->count(),
                'first_post_title' => $user->posts->first()?->title,
                'comment_count' => $user->posts->sum(fn ($p) => $p->comments->count()),
            ];
        }

        return $stats;
    }

    public function notifyAllUsers(string $subject, string $body): void
    {
        $users = User::all();

        foreach ($users as $user) {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
        }
    }

    public function findByEmailRaw(string $email): ?User
    {
        return User::whereRaw("email = '$email'")->first();
    }
}
