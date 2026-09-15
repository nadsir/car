<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin', fn () => view('welcome'));

Route::get('/', fn () => view('welcome'));

Route::get('/store', fn () => view('welcome'));

Route::get('/cart', fn () => view('welcome'));

Route::get('/wishlist', fn () => view('welcome'));

Route::get('/login', fn () => view('welcome'));

Route::get('/register', fn () => view('welcome'));

Route::get('/account', fn () => view('welcome'));

Route::get('/products/{id}', fn () => view('welcome'))
    ->whereNumber('id');
