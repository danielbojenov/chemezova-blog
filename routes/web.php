<?php

use App\Http\Controllers\AffiliateLinkRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    ds()->routes();
    ds('Hello world!');

    return view('welcome');
});

Route::get('/go/{slug}', AffiliateLinkRedirectController::class)->name('affiliate-links.redirect');
