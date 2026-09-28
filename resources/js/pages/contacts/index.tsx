import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import SortableHead from '@/components/sortable-head';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexFilters } from '@/hooks/use-index-filters';
import type { Contact, Paginated, SortDirection } from '@/types';

type Filters = {
    search: string;
    mine: boolean;
    sortBy: string;
    sortDirection: SortDirection;
};

const defaultFilters: Filters = {
    search: '',
    mine: false,
    sortBy: 'last_name',
    sortDirection: 'asc',
};

export default function ContactsIndex({
    contacts,
    filters: initialFilters,
    can,
}: {
    contacts: Paginated<Contact>;
    filters: Filters;
    can: { create: boolean };
}) {
    const { filters, update } = useIndexFilters(
        ContactController.index.url(),
        initialFilters,
        defaultFilters,
    );
    const [deleting, setDeleting] = useState<Contact | null>(null);
    const [processing, setProcessing] = useState(false);

    const destroy = () => {
        if (!deleting) {
            return;
        }

        router.delete(ContactController.destroy.url(deleting.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setDeleting(null),
        });
    };

    const sort = (sortBy: string, sortDirection: SortDirection) =>
        update({ sortBy, sortDirection });

    return (
        <>
            <Head title="Contacts" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading title="Contacts" />

                    {can.create && (
                        <Button asChild>
                            <Link href={ContactController.create()}>
                                <Plus />
                                New contact
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-4">
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
                            placeholder="Search name, email or company"
                            aria-label="Search contacts"
                            className="pl-9"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="mine"
                            checked={filters.mine}
                            onCheckedChange={(checked) =>
                                update({ mine: checked === true })
                            }
                        />
                        <Label htmlFor="mine">Only mine</Label>
                    </div>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <SortableHead
                                column="last_name"
                                sortBy={filters.sortBy}
                                sortDirection={filters.sortDirection}
                                onSort={sort}
                            >
                                Name
                            </SortableHead>
                            <TableHead>Company</TableHead>
                            <SortableHead
                                column="email"
                                sortBy={filters.sortBy}
                                sortDirection={filters.sortDirection}
                                onSort={sort}
                            >
                                Email
                            </SortableHead>
                            <TableHead>Owner</TableHead>
                            <SortableHead
                                column="created_at"
                                sortBy={filters.sortBy}
                                sortDirection={filters.sortDirection}
                                onSort={sort}
                            >
                                Created
                            </SortableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {contacts.data.map((contact) => (
                            <TableRow key={contact.id}>
                                <TableCell>
                                    <Link
                                        href={ContactController.show(
                                            contact.id,
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {contact.full_name}
                                    </Link>
                                    {contact.job_title && (
                                        <p className="text-sm text-muted-foreground">
                                            {contact.job_title}
                                        </p>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {contact.company ? (
                                        <Link
                                            href={CompanyController.show(
                                                contact.company.id,
                                            )}
                                            className="hover:underline"
                                        >
                                            {contact.company.name}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </TableCell>
                                <TableCell>{contact.email ?? '—'}</TableCell>
                                <TableCell>
                                    {contact.owner?.name ?? '—'}
                                </TableCell>
                                <TableCell>
                                    {contact.created_at
                                        ? new Date(
                                              contact.created_at,
                                          ).toLocaleDateString()
                                        : '—'}
                                </TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {contact.can.update && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={ContactController.edit(
                                                        contact.id,
                                                    )}
                                                    aria-label="Edit"
                                                >
                                                    <Pencil />
                                                </Link>
                                            </Button>
                                        )}
                                        {contact.can.delete && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Delete"
                                                onClick={() =>
                                                    setDeleting(contact)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {contacts.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="text-muted-foreground"
                                >
                                    No contacts found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <Pagination {...contacts.meta} />
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete ${deleting?.full_name ?? ''}?`}
                description="The contact is moved to the archive."
                processing={processing}
                onConfirm={destroy}
            />
        </>
    );
}

ContactsIndex.layout = {
    breadcrumbs: [{ title: 'Contacts', href: ContactController.index() }],
};
