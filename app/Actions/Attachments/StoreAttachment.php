<?php

namespace App\Actions\Attachments;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StoreAttachment
{
    /**
     * Move an uploaded file into the record's "attachments" collection.
     */
    public function __invoke(HasMedia $model, UploadedFile $file): Media
    {
        $originalName = $file->getClientOriginalName();
        $extension = Str::lower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'file';

        return $model->addMedia($file)
            ->usingName(pathinfo($originalName, PATHINFO_FILENAME))
            ->usingFileName($baseName.($extension !== '' ? '.'.$extension : ''))
            ->withCustomProperties(['uploaded_by' => auth()->id()])
            ->toMediaCollection('attachments');
    }

    /**
     * Validation rules for a single uploaded attachment.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'file',
            'max:'.config('crm.attachments.max_size_kb'),
            'mimes:'.implode(',', config('crm.attachments.mimes')),
            'mimetypes:'.implode(',', config('crm.attachments.mime_types')),
        ];
    }
}
