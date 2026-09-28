import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexFilters } from '@/hooks/use-index-filters';
import type { PaginationLink } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string | null;
};

/** A plain Laravel paginator (not an API resource collection). */
type UserPage = {
    data: UserRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

export default function Users({
    users,
    filters: initialFilters,
    roles,
}: {
    users: UserPage;
    filters: { search: string };
    roles: { value: string; label: string }[];
}) {
    const { filters, update } = useIndexFilters(
        UserController.index.url(),
        initialFilters,
        { search: '' },
    );

    const setRole = (user: UserRow, role: string) =>
        router.patch(
            UserController.updateRole.url(user.id),
            { role },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-6 p-4">
                <Heading title="Users" />

                <div className="relative w-full max-w-sm">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={filters.search}
                        onChange={(event) =>
                            update(
                                { search: event.target.value },
                                { debounce: 300 },
                            )
                        }
                        placeholder="Search name or email"
                        aria-label="Search users"
                        className="pl-9"
                    />
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Role</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.map((user) => (
                            <TableRow key={user.id}>
                                <TableCell className="font-medium">
                                    {user.name}
                                </TableCell>
                                <TableCell>{user.email}</TableCell>
                                <TableCell>
                                    <NativeSelect
                                        className="h-8 max-w-40"
                                        value={user.role ?? ''}
                                        onChange={(event) =>
                                            setRole(user, event.target.value)
                                        }
                                        aria-label={`Role for ${user.name}`}
                                    >
                                        {user.role === null && (
                                            <option value="" disabled>
                                                No role
                                            </option>
                                        )}
                                        {roles.map((role) => (
                                            <option
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                <Pagination {...users} />
            </div>
        </>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: UserController.index() }],
};
