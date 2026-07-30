<?php

use App\Http\Controllers\AffiliateLinkRedirectController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/categories/{category:slug}', CategoryController::class)->name('categories.show');

Route::get('/go/{slug}', AffiliateLinkRedirectController::class)->name('affiliate-links.redirect');
