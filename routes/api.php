<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PlaceVoteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\PushSubscriptionController;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
*/

// AUTH
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// User profile update — any authenticated user can update their own profile
Route::middleware('auth:sanctum')->put('/users/{id}', [UserController::class, 'update']);

// Users management — only admins can view/create/delete
Route::middleware(['auth:sanctum','role:admin'])->group(function () {
    Route::apiResource('users', UserController::class, ['only' => ['index', 'store', 'show', 'destroy']]);
});





Route::middleware('auth:sanctum')->group(function () {

    // Places
    Route::apiResource('places', PlaceController::class);

    // Photos
    Route::post('/photos', [PhotoController::class, 'store']);

    // Reviews
    //Route::post('/reviews', [ReviewController::class, 'store']);

    // Votes
    Route::post('/votes', [PlaceVoteController::class, 'store']);
    Route::delete('/votes/{place_id}', [PlaceVoteController::class, 'destroy']);
});


// Obtener fotos sin autenticación
Route::get('/photos/{id}', [PhotoController::class, 'show']);


Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('reviews', ReviewController::class)->except(['create', 'edit']);
    
    // Push Notifications
    Route::post('/save-subscription', [PushSubscriptionController::class, 'store']);
    Route::post('/delete-subscription', [PushSubscriptionController::class, 'destroy']);
});

// Public endpoint for VAPID public key
Route::get('/vapid-public-key', [PushSubscriptionController::class, 'publicKey']);
