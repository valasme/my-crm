<?php

use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('companies', 'pages::companies.index')
        ->name('companies.index')
        ->middleware('throttle:companies-read');

    Route::livewire('companies/create', 'pages::companies.create')
        ->name('companies.create')
        ->middleware('throttle:companies-read');

    Route::livewire('companies/{company}/edit', 'pages::companies.edit')
        ->name('companies.edit')
        ->whereNumber('company')
        ->middleware('throttle:companies-read');

    Route::livewire('companies/{company}', 'pages::companies.show')
        ->name('companies.show')
        ->whereNumber('company')
        ->middleware('throttle:companies-read');

    Route::resource('companies', CompanyController::class)
        ->only(['store', 'update', 'destroy'])
        ->whereNumber('company')
        ->middleware('throttle:companies-write');
});

require __DIR__.'/settings.php';
