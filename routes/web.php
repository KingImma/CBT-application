<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/onboarding', 'welcome');
Route::view('/signin', 'welcome');
