<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Manager => __('Manager'),
            self::Sales => __('Sales'),
            self::Viewer => __('Viewer'),
        };
    }

    /**
     * Permissions granted to the role. Admins also bypass every check via Gate::before.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $crud = fn (string $resource) => array_map(
            fn (string $ability) => "{$resource}.{$ability}",
            ['view', 'create', 'update', 'delete'],
        );

        return match ($this) {
            self::Admin => Permission::all(),
            self::Manager => [
                ...$crud('companies'),
                ...$crud('contacts'),
                Permission::NotesCreate,
                Permission::ManageAnyRecord,
            ],
            self::Sales => [
                ...$crud('companies'),
                ...$crud('contacts'),
                Permission::NotesCreate,
            ],
            self::Viewer => ['companies.view', 'contacts.view'],
        };
    }
}
