<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\IssueController;
use Illuminate\Support\Facades\Route;

Route::controller(IssueController::class)->group(function (): void {
    Route::get('issues', 'index');
    Route::post('issues', 'store');
    Route::get('issues/{issue}', 'show');
    Route::patch('issues/{issue}', 'update');
});

Route::post('issues/{issue}/comments', [CommentController::class, 'store']);
