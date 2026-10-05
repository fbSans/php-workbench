<?php

use Base\Route;
use App\Controllers\HomeController;
use Documents\Controllers\DirectoryController;
use Documents\Controllers\DocumentController;
use QrCode\Controllers\QRCodeController;
use Users\Controllers\UserController;
use Users\Controllers\ProfileController;

Route::get('/', [HomeController::class, 'index'])->middleware('auth');

Route::get('/login', [UserController::class, 'login_page']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth');

Route::get('/documents', [DocumentController::class, 'index'])->middleware('auth');
Route::post('/document/save', [DocumentController::class, 'save'])->middleware('auth', 'csrf');
Route::post('/directory/save', [DirectoryController::class, 'save'])->middleware('auth', 'csrf');

Route::get('/profile', [ProfileController::class, 'index'])->middleware('auth');
Route::post('/changePassword', [ProfileController::class, 'changePassword'])->middleware('auth', 'csrf');

Route::get('/qrcode', [QRCodeController::class, 'index']);
Route::get('/url_qrcode', [QRCodeController::class, 'generate_uri_code']);