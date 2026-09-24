<?php

namespace App\Actions\Attachments;

use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StoreAttachment
{
    /**
     * Copy a Livewire upload into the record's "attachments" collection.
     *
     * The upload is read as a stream so this works whether Livewire's
     * temporary uploads live on local disk or in Azure Blob Storage.
     */
    public function __invoke(HasMedia $model, TemporaryUploadedFile $file): Media
    {
        $originalName = $file->getClientOriginalName();
        $extension = Str::lower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'file';

        return $model->addMediaFromStream($file->readStream())
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
