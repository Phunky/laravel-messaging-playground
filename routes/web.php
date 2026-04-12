<?php

use Illuminate\Support\Facades\Route;

Route::view('/welcome', 'welcome');

Route::livewire('/login', 'pages::auth.login')
    ->middleware(['guest:'.config('fortify.guard')])
    ->name('login');

Route::livewire('/settings/profile', 'pages::settings.profile')
    ->middleware(['auth'])
    ->name('settings.profile');

Route::livewire('/', 'pages::chat')->middleware('auth');
