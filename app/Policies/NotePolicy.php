<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    /**
     * Notes are visible to anyone who can see the record they belong to.
     */
    public function view(User $user, Note $note): bool
    {
        return $user->can('view', $note->notable);
    }

    public function create(User $user, Company|Contact $notable): bool
    {
        return $user->can(Permission::NotesCreate) && $user->can('view', $notable);
    }

    public function update(User $user, Note $note): bool
    {
        return $this->authoredOrManagesAny($user, $note);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->authoredOrManagesAny($user, $note);
    }

    private function authoredOrManagesAny(User $user, Note $note): bool
    {
        return $note->isAuthoredBy($user) || $user->can(Permission::ManageAnyRecord);
    }
}
