<?php

use App\Http\Controllers\AffiliateLinkRedirectController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/articles/{article:slug}', ArticleController::class)->name('articles.show');

Route::get('/categories/{category:slug}', CategoryController::class)->name('categories.show');

Route::get('/tags/{tag:slug}', TagController::class)->name('tags.show');

Route::get('/go/{slug}', AffiliateLinkRedirectController::class)->name('affiliate-links.redirect');

/*
|--------------------------------------------------------------------------
| Standing pages
|--------------------------------------------------------------------------
|
| These match at the root of the site, so they must stay last: Laravel matches
| routes in registration order, and `/{page:slug}` would otherwise swallow any
| single-segment route declared below it. Slugs that would collide with a real
| route are rejected at the point of authoring instead — see
| App\Support\Site\ReservedSlugs.
|
*/

Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');

// The child parameter is named for the `children()` relation on purpose: a nested,
// custom-keyed binding is scoped to it automatically, so `/legal/privacy-policy` only
// resolves when that page really is filed under that hub.
Route::get('/{parent:slug}/{child:slug}', [PageController::class, 'showChild'])->name('pages.show.child');
