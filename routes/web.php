<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('LandingPage.index');
});

Route::get('/track-application', function () {
    return view('LandingPage.track');
})->name('track');
