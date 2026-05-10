<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TaskController;
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

    Route::livewire('contacts', 'pages::contacts.index')
        ->name('contacts.index')
        ->middleware('throttle:contacts-read');

    Route::livewire('contacts/create', 'pages::contacts.create')
        ->name('contacts.create')
        ->middleware('throttle:contacts-read');

    Route::livewire('contacts/{contact}/edit', 'pages::contacts.edit')
        ->name('contacts.edit')
        ->whereNumber('contact')
        ->middleware('throttle:contacts-read');

    Route::livewire('contacts/{contact}', 'pages::contacts.show')
        ->name('contacts.show')
        ->whereNumber('contact')
        ->middleware('throttle:contacts-read');

    Route::resource('contacts', ContactController::class)
        ->only(['store', 'update', 'destroy'])
        ->whereNumber('contact')
        ->middleware('throttle:contacts-write');

    Route::livewire('activities', 'pages::activities.index')
        ->name('activities.index')
        ->middleware('throttle:activities-read');

    Route::livewire('activities/create', 'pages::activities.create')
        ->name('activities.create')
        ->middleware('throttle:activities-read');

    Route::livewire('activities/{activity}/edit', 'pages::activities.edit')
        ->name('activities.edit')
        ->whereNumber('activity')
        ->middleware('throttle:activities-read');

    Route::livewire('activities/{activity}', 'pages::activities.show')
        ->name('activities.show')
        ->whereNumber('activity')
        ->middleware('throttle:activities-read');

    Route::resource('activities', ActivityController::class)
        ->only(['store', 'update', 'destroy'])
        ->whereNumber('activity')
        ->middleware('throttle:activities-write');

    Route::livewire('tasks', 'pages::tasks.index')
        ->name('tasks.index')
        ->middleware('throttle:tasks-read');

    Route::livewire('tasks/create', 'pages::tasks.create')
        ->name('tasks.create')
        ->middleware('throttle:tasks-read');

    Route::livewire('tasks/{task}/edit', 'pages::tasks.edit')
        ->name('tasks.edit')
        ->whereNumber('task')
        ->middleware('throttle:tasks-read');

    Route::livewire('tasks/{task}', 'pages::tasks.show')
        ->name('tasks.show')
        ->whereNumber('task')
        ->middleware('throttle:tasks-read');

    Route::resource('tasks', TaskController::class)
        ->only(['store', 'update', 'destroy'])
        ->whereNumber('task')
        ->middleware('throttle:tasks-write');
});

require __DIR__.'/settings.php';
