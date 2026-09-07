<?php

use Routes\Route;
use App\Controllers\HomeController;
use Documents\Controllers\DocumentController;

Route::get('/', [HomeController::class, 'index']);
Route::get('/documents', [DocumentController::class, 'index']);