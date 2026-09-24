<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private attachments after checking the user can view the record
 * the file belongs to.
 */
class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, Media $media, ?string $conversion = null): StreamedResponse|RedirectResponse
    {
        $record = $media->model;

        abort_if($record === null, 404);
        Gate::authorize('view', $record instanceof Note ? $record->notable : $record);

        $conversion ??= '';

        if ($conversion !== '') {
            abort_unless(in_array($conversion, ['thumb', 'preview'], true) && $media->hasGeneratedConversion($conversion), 404);
        }

        if (config('crm.attachments.download_strategy') === 'redirect') {
            return redirect()->away($media->getTemporaryUrl(
                now()->addMinutes(config('crm.attachments.temporary_url_minutes')),
                $conversion,
            ));
        }

        $disk = $conversion === '' ? $media->disk : $media->conversions_disk;
        $inline = $conversion !== '' || $request->boolean('inline');
        $filename = $conversion === '' ? $media->file_name : $media->name.'-'.$conversion.'.'.pathinfo($media->getPath($conversion), PATHINFO_EXTENSION);

        return Storage::disk($disk)->response(
            $media->getPathRelativeToRoot($conversion),
            $filename,
            ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff'],
            $inline ? 'inline' : 'attachment',
        );
    }
}
