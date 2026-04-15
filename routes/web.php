<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('LandingPage.index');
});

Route::get('/track-application', function () {
    return view('LandingPage.track');
})->name('track');

Route::get('/register', function () {
    return view('LandingPage.register');
})->name('register');

Route::get('/login', function () {
    return view('LandingPage.login');
})->name('login');
