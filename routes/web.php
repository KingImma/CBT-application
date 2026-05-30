<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['message' => 'CBT Application']));
Route::get('/up', fn () => response()->json(['message' => 'ok']));

// DEV TESTING ONLY — do not commit these routes
Route::get('/_test/exams', fn () => view('dev.test-exams'));
Route::get('/_test/exams/student', fn () => view('dev.test-student'));
