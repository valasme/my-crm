<?php

use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::view("/", "welcome")->name("home");

Route::middleware(["auth", "verified"])->group(function () {
    Route::view("dashboard", "dashboard")->name("dashboard");
});

Route::middleware(["auth"])->group(function () {
    Route::resource("companies", CompanyController::class)
        ->except(["store", "update", "destroy"])
        ->whereNumber("company")
        ->middleware("throttle:companies-read");

    Route::resource("companies", CompanyController::class)
        ->only(["store", "update", "destroy"])
        ->whereNumber("company")
        ->middleware("throttle:companies-write");
});

require __DIR__ . "/settings.php";
