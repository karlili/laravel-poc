<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | The role given to users when they register. Admins can change it
    | afterwards.
    |
    */

    'default_role' => env('CRM_DEFAULT_ROLE', 'viewer'),

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | "download_strategy" is "stream" (the app proxies the file after the
    | authorisation check) or "redirect" (a short-lived signed URL, which
    | needs a disk that can create one, such as Azure with a shared key).
    |
    */

    'attachments' => [
        'max_size_kb' => 1024 * (int) env('MEDIA_MAX_FILE_SIZE_MB', 20),
        'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
        'mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'download_strategy' => env('MEDIA_DOWNLOAD_STRATEGY', 'stream'),
        'temporary_url_minutes' => (int) env('MEDIA_TEMPORARY_URL_MINUTES', 5),
    ],

];
