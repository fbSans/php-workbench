<?php

use Routes\Route;
use App\Controllers\HomeController;
use Documents\Controllers\DocumentController;
use Users\Controllers\UserController;



Route::get('/', [HomeController::class, 'index'])->middleware('auth');

Route::get('/documents', [DocumentController::class, 'index'])->middleware('auth');
Route::get('/login', [UserController::class, 'login_page']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth');
