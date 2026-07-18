<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    ds()->routes();
    ds('Hello world!');

    return view('welcome');
});
