<?php

use Routes\Route;
use App\Controllers\HomeController;
use Documents\Controllers\DocumentController;
use Documents\Models\Document;
use Documents\Models\File;
use Users\Controllers\UserController;
use Users\Models\User;

Route::get('/', [HomeController::class, 'index'])->middleware('auth');

Route::get('/documents', [DocumentController::class, 'index'])->middleware('auth');
Route::get('/login', [UserController::class, 'login_page']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth');
Route::post('/document/save', [DocumentController::class, 'save'])->middleware('auth');
Route::get('/debug', function (){
    $document = Document::first();
    var_dump($document->file);
    exit;
});