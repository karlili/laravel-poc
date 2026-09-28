<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('companies', CompanyController::class);
    Route::resource('contacts', ContactController::class);

    Route::post('companies/{company}/notes', [NoteController::class, 'storeForCompany'])->name('companies.notes.store');
    Route::post('contacts/{contact}/notes', [NoteController::class, 'storeForContact'])->name('contacts.notes.store');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    Route::post('companies/{company}/attachments', [AttachmentController::class, 'storeForCompany'])->name('companies.attachments.store');
    Route::post('contacts/{contact}/attachments', [AttachmentController::class, 'storeForContact'])->name('contacts.attachments.store');
    Route::delete('attachments/{media}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('media/{media}/{conversion?}', MediaDownloadController::class)->name('media.show');

    Route::middleware('can:'.Permission::ManageUsers)->group(function () {
        Route::get('admin/users', [UserController::class, 'index'])->name('admin.users');
        Route::patch('admin/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.role');
    });
});

require __DIR__.'/settings.php';
