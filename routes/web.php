<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Customer Portal Routes
require __DIR__.'/customer.php';
