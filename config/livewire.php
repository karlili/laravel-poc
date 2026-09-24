<?php

// Only the keys that differ from Livewire's defaults. Laravel merges this file
// over the package config one top-level key at a time, so each key listed
// here must be complete.

return [

    /*
    |---------------------------------------------------------------------------
    | Temporary File Uploads
    |---------------------------------------------------------------------------
    |
    | On Azure the app runs as several replicas, so temporary uploads go to
    | shared Blob Storage (LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=azure) rather
    | than a replica's local disk. The size limit follows the attachment limit.
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:'.(1024 * (int) env('MEDIA_MAX_FILE_SIZE_MB', 20))],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
