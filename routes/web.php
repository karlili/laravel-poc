<?php

use App\Enums\Permission;
use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\MediaDownloadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['guest', 'throttle:20,1'])->prefix('auth/microsoft')->name('auth.microsoft.')->group(function () {
    Route::get('redirect', [MicrosoftController::class, 'redirect'])->name('redirect');
    Route::get('callback', [MicrosoftController::class, 'callback'])->name('callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('companies', 'pages::companies.index')->name('companies.index');
    Route::livewire('companies/create', 'pages::companies.form')->name('companies.create');
    Route::livewire('companies/{company}', 'pages::companies.show')->name('companies.show');
    Route::livewire('companies/{company}/edit', 'pages::companies.form')->name('companies.edit');

    Route::livewire('contacts', 'pages::contacts.index')->name('contacts.index');
    Route::livewire('contacts/create', 'pages::contacts.form')->name('contacts.create');
    Route::livewire('contacts/{contact}', 'pages::contacts.show')->name('contacts.show');
    Route::livewire('contacts/{contact}/edit', 'pages::contacts.form')->name('contacts.edit');

    Route::get('media/{media}/{conversion?}', MediaDownloadController::class)->name('media.show');

    Route::livewire('admin/users', 'pages::admin.users')
        ->middleware('can:'.Permission::ManageUsers)
        ->name('admin.users');
});

require __DIR__.'/settings.php';
