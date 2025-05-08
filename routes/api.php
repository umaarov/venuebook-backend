<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeddingHallController;
use App\Http\Controllers\Api\WeddingHallOwnerController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/districts', [WeddingHallController::class, 'getDistricts']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'update']);
    Route::delete('/profile', [UserController::class, 'destroy']);

    Route::get('/wedding-halls', [WeddingHallController::class, 'index']);
    Route::get('/wedding-halls/{id}', [WeddingHallController::class, 'show']);

    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::get('/reservations/{id}', [ReservationController::class, 'show']);
    Route::post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
    Route::get('/my-reservations', [ReservationController::class, 'userReservations']);

    Route::middleware('check.owner')->group(function () {
        Route::post('/wedding-halls', [WeddingHallController::class, 'store']);
        Route::put('/wedding-halls/{id}', [WeddingHallController::class, 'update']);
        Route::post('/wedding-halls/{id}/images', [WeddingHallController::class, 'uploadImage']);
        Route::delete('/wedding-halls/images/{imageId}', [WeddingHallController::class, 'deleteImage']);

        Route::get('/owner/wedding-halls', [WeddingHallOwnerController::class, 'myWeddingHalls']);
        Route::get('/owner/reservations', [WeddingHallOwnerController::class, 'myReservations']);
        Route::post('/owner/reservations/{id}/cancel', [WeddingHallOwnerController::class, 'cancelReservation']);
    });

    Route::middleware('check.admin')->group(function () {
        Route::post('/admin/wedding-halls', [WeddingHallController::class, 'store']);
        Route::put('/admin/wedding-halls/{id}', [WeddingHallController::class, 'update']);
        Route::delete('/admin/wedding-halls/{id}', [WeddingHallController::class, 'destroy']);
        Route::post('/admin/wedding-halls/{id}/approve', [AdminController::class, 'approveWeddingHall']);
        Route::post('/admin/wedding-halls/{id}/reject', [AdminController::class, 'rejectWeddingHall']);

        Route::post('/admin/owners', [AdminController::class, 'addOwner']);
        Route::post('/admin/associate-owner', [AdminController::class, 'associateOwner']);
        Route::get('/admin/owners', [AdminController::class, 'listOwners']);
    });
});
