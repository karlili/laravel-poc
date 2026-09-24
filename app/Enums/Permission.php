<?php

namespace App\Enums;

/**
 * Permission names used with spatie/laravel-permission.
 *
 * CRUD permissions follow "{resource}.{ability}". Ownership is enforced by
 * policies: without "records.manage-any" a user may only change records
 * they own (companies, contacts) or wrote (notes).
 */
final class Permission
{
    public const ManageAnyRecord = 'records.manage-any';

    public const NotesCreate = 'notes.create';

    public const ManageUsers = 'users.manage';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (['companies', 'contacts'] as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $ability) {
                $permissions[] = "{$resource}.{$ability}";
            }
        }

        return [...$permissions, self::NotesCreate, self::ManageAnyRecord, self::ManageUsers];
    }
}
