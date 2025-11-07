<?php

use App\Helpers\ApiFormatter;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});


Route::get('test', function() {
    $user = User::with(['role', 'wilaya'])->find(4); // eager load relations
    dd(ApiFormatter::auth($user));
});

require __DIR__.'/settings.php';
