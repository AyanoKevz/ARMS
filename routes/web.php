<?php

use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('LandingPage.index');
});

Route::get('/track-application', function () {
    return view('LandingPage.track');
})->name('track');

// Registration — show form
Route::get('/register', function () {
    return view('LandingPage.register');
})->name('register');

// Registration — process form & send verification email
Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');

// Email verification link
Route::get('/verify-email/{token}', [RegistrationController::class, 'verify'])->name('register.verify');

Route::get('/login', function () {
    return view('LandingPage.login');
})->name('login');
