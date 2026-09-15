<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin', fn () => view('welcome'));

Route::get('/', fn () => view('welcome'));

Route::get('/store', fn () => view('welcome'));

Route::get('/products/{id}', fn () => view('welcome'))
    ->whereNumber('id');
