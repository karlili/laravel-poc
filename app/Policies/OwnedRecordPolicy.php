<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;

/**
 * Shared rules for CRM records that have an owner.
 *
 * Anyone with "{resource}.view" can see records. Changing or deleting a
 * record also needs ownership unless the user has "records.manage-any".
 */
abstract class OwnedRecordPolicy
{
    /**
     * The permission prefix, for example "companies".
     */
    abstract protected function resource(): string;

    public function viewAny(User $user): bool
    {
        return $user->can("{$this->resource()}.view");
    }

    public function view(User $user, Company|Contact $record): bool
    {
        return $user->can("{$this->resource()}.view");
    }

    public function create(User $user): bool
    {
        return $user->can("{$this->resource()}.create");
    }

    public function update(User $user, Company|Contact $record): bool
    {
        return $user->can("{$this->resource()}.update") && $this->ownsOrManagesAny($user, $record);
    }

    public function delete(User $user, Company|Contact $record): bool
    {
        return $user->can("{$this->resource()}.delete") && $this->ownsOrManagesAny($user, $record);
    }

    public function restore(User $user, Company|Contact $record): bool
    {
        return $user->can(Permission::ManageAnyRecord);
    }

    public function forceDelete(User $user, Company|Contact $record): bool
    {
        return false;
    }

    /**
     * Whether the user may reassign the record to someone else.
     */
    public function assign(User $user): bool
    {
        return $user->can(Permission::ManageAnyRecord);
    }

    protected function ownsOrManagesAny(User $user, Company|Contact $record): bool
    {
        return $record->isOwnedBy($user) || $user->can(Permission::ManageAnyRecord);
    }
}
