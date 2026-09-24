<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property int $id
 * @property string $notable_type
 * @property int $notable_id
 * @property string $body
 * @property int|null $author_id
 * @property-read Company|Contact $notable
 * @property-read User|null $author
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['body'])]
class Note extends Model implements HasMedia
{
    /** @use HasFactory<NoteFactory> */
    use HasAttachments, HasFactory, LogsActivity;

    /**
     * @return MorphTo<Model, $this>
     */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isAuthoredBy(User $user): bool
    {
        return $this->author_id !== null && $this->author_id === $user->id;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['body'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
