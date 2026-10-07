<?php

use Atlas\Http\Controllers\PageController;
use Atlas\Http\Controllers\RenderController;
use Atlas\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('index');
Route::post('pages', [PageController::class, 'store'])->name('pages.store');
Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
Route::get('pages/{page}/preview', [PageController::class, 'preview'])->name('pages.preview');
Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');

Route::put('api/pages/{page}', [PageController::class, 'update'])->name('api.pages.update');
Route::post('api/render', RenderController::class)->name('api.render');
Route::post('api/upload', UploadController::class)->name('api.upload');
