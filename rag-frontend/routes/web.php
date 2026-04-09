<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthController;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
Route::post('/signup', [AuthController::class, 'signup'])->name('signup.post');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', [ChatController::class, 'index'])->name('dashboard');
Route::post('/upload', [ChatController::class, 'upload']);
Route::post('/ocr-upload', [ChatController::class, 'ocrUpload']); // The new OCR route
Route::post('/ask', [ChatController::class, 'ask']);
Route::get('/chat/{session_id}/messages', [ChatController::class, 'getMessages']);
