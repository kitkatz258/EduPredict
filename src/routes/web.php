<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/demo/table', 'pages.demo-table')->name('demo.table');
