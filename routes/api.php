<?php

use App\Http\Controllers\AuthorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('books/export', [BookController::class, 'export']);
Route::apiResource('books', BookController::class);
Route::apiResource('authors', AuthorController::class);
