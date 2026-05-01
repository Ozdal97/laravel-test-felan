<?php

declare(strict_types=1);

use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/active-with-posts', [UserController::class, 'activeWithPosts']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::patch('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

    Route::get('posts', [PostController::class, 'index']);
    Route::get('posts/{id}', [PostController::class, 'show']);
    Route::get('users/{userId}/posts', [PostController::class, 'byUser']);
    Route::post('posts', [PostController::class, 'store']);
    Route::delete('posts/{post}', [PostController::class, 'destroy']);
});
