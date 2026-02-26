<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Api\Staff\SuperviseController;
use App\Http\Controllers\Api\Staff\ReviewController;
use App\Http\Controllers\Api\Staff\PreDefenseController;
use App\Http\Controllers\Api\Staff\FinalDefenseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rute yang tidak memerlukan autentikasi
Route::post('/login', [LoginController::class, 'login']);
// Route::post('/register', [RegisterController::class, 'register']); // Disabled

// Rute yang memerlukan autentikasi JWT
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/user', [LoginController::class, 'me']);

    // Rute untuk Staff
    Route::prefix('staff')->name('staff.')->group(function () {
        // Rute untuk Supervisi
        Route::prefix('supervise')->name('supervise.')->group(function () {
            Route::get('/', [SuperviseController::class, 'index'])->name('index');
            Route::get('/{id}', [SuperviseController::class, 'show'])->name('show');
            Route::get('/{id}/approvals', [SuperviseController::class, 'getApprovals'])->name('approvals');
            Route::post('/approvals/{approvalId}/approve', [SuperviseController::class, 'approve'])->name('approve');
        });

        // Rute untuk Review
        Route::prefix('review')->name('review.')->group(function () {
            Route::get('/', [ReviewController::class, 'index'])->name('index');
            Route::get('/{id}', [ReviewController::class, 'show'])->name('show');
            Route::post('/{researchId}/submit', [ReviewController::class, 'submit'])->name('submit');
        });

        // Rute untuk Pre-Defense
        Route::prefix('pre-defense')->name('pre-defense.')->group(function () {
            Route::get('/', [PreDefenseController::class, 'index'])->name('index');
            Route::get('/{id}/participants', [PreDefenseController::class, 'getParticipants'])->name('participants');
            Route::get('/participant/{id}', [PreDefenseController::class, 'getParticipantDetail'])->name('participant.detail');
            Route::post('/examiner/{id}/presence', [PreDefenseController::class, 'toggleExaminerPresence'])->name('examiner.presence');
            Route::get('/staff/search', [PreDefenseController::class, 'searchStaff'])->name('staff.search');
            Route::post('/participant/{id}/add-examiner', [PreDefenseController::class, 'addExaminer'])->name('participant.add_examiner');
            Route::get('/score-guide', [PreDefenseController::class, 'getScoreGuide'])->name('score_guide');
            Route::post('/participant/{id}/score', [PreDefenseController::class, 'submitScore'])->name('participant.score');
        });

        // Rute untuk Final-Defense
        Route::prefix('final-defense')->name('final-defense.')->group(function () {
            Route::get('/', [FinalDefenseController::class, 'index'])->name('index');
            Route::get('/{eventId}/rooms', [FinalDefenseController::class, 'getRooms'])->name('rooms');
            Route::get('/room/{roomId}', [FinalDefenseController::class, 'getRoomDetail'])->name('room.detail');
            Route::post('/room/{roomId}/switch-moderator', [FinalDefenseController::class, 'switchModerator'])->name('room.switch_moderator');
            Route::post('/room/{roomId}/examiner/{examinerId}/presence', [FinalDefenseController::class, 'toggleExaminerPresence'])->name('room.examiner.presence');
            Route::post('/applicant/{applicantId}/score', [FinalDefenseController::class, 'submitScore'])->name('applicant.score');
            Route::get('/score-guide', [FinalDefenseController::class, 'getScoreGuide'])->name('score_guide');
        });
    });

    // Staff lookup for frontend autocomplete/search
    Route::get('/staff/search', [\App\Http\Controllers\Api\Staff\ResearchController::class, 'searchStaff'])->name('staff.search');
});
