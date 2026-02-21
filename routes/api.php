<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Api\Staff\ResearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rute yang tidak memerlukan autentikasi
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [RegisterController::class, 'register']);

// Rute yang memerlukan autentikasi JWT
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/user', [LoginController::class, 'me']);

    // Rute untuk Staff Research
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/researches', [ResearchController::class, 'index'])->name('researches.index');
        Route::get('/researches/{id}', [ResearchController::class, 'show'])->name('researches.show');
        Route::get('/researches/{id}/approvals', [ResearchController::class, 'getApprovals'])->name('researches.approvals');
        Route::post('/approvals/{approvalId}/approve', [ResearchController::class, 'approve'])->name('approvals.approve');
        Route::get('/researches/{id}/events', [ResearchController::class, 'getEvents'])->name('researches.events');

        // Rute untuk Review
        Route::get('/reviews', [ResearchController::class, 'getReviews'])->name('reviews.index');
        Route::get('/reviews/{id}', [ResearchController::class, 'getReviewDetail'])->name('reviews.show');
        // Mengubah {reviewId} menjadi {researchId} agar lebih akurat
        Route::post('/reviews/{researchId}/submit', [ResearchController::class, 'submitReview'])->name('reviews.submit');
    });
});
