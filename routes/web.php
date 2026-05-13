<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['message' => 'CBT Application']));
Route::get('/up', fn () => response()->json(['message' => 'ok']));
