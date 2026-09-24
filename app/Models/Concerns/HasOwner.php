<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CRM record assigned to (owned by) a user.
 *
 * @property int|null $owner_id
 * @property-read User|null $owner
 */
trait HasOwner
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id !== null && $this->owner_id === $user->id;
    }
}
