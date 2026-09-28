import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import SortableHead from '@/components/sortable-head';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import type { Company, Paginated, SortDirection } from '@/types';

type Filters = {
    search: string;
    industry: string;
    mine: boolean;
    sortBy: string;
    sortDirection: SortDirection;
};

const defaultFilters: Filters = {
    search: '',
    industry: '',
    mine: false,
    sortBy: 'name',
    sortDirection: 'asc',
};

export default function CompaniesIndex({
    companies,
    filters: initialFilters,
    industries,
    can,
}: {
    companies: Paginated<Company>;
    filters: Filters;
    industries: string[];
    can: { create: boolean };
}) {
    const { filters, update } = useIndexFilters(
        CompanyController.index.url(),
        initialFilters,
        defaultFilters,
    );
    const [deleting, setDeleting] = useState<Company | null>(null);
    const [processing, setProcessing] = useState(false);

    const destroy = () => {
        if (!deleting) {
            return;
        }

        router.delete(CompanyController.destroy.url(deleting.id), {
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
            <Head title="Companies" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading title="Companies" />

                    {can.create && (
                        <Button asChild>
                            <Link href={CompanyController.create()}>
                                <Plus />
                                New company
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
                            placeholder="Search name, domain or email"
                            aria-label="Search companies"
                            className="pl-9"
                        />
                    </div>

                    <NativeSelect
                        value={filters.industry}
                        onChange={(event) =>
                            update({ industry: event.target.value })
                        }
                        aria-label="Industry"
                        className="max-w-52"
                    >
                        <option value="">All industries</option>
                        {industries.map((industry) => (
                            <option key={industry} value={industry}>
                                {industry}
                            </option>
                        ))}
                    </NativeSelect>

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
                                column="name"
                                sortBy={filters.sortBy}
                                sortDirection={filters.sortDirection}
                                onSort={sort}
                            >
                                Name
                            </SortableHead>
                            <SortableHead
                                column="industry"
                                sortBy={filters.sortBy}
                                sortDirection={filters.sortDirection}
                                onSort={sort}
                            >
                                Industry
                            </SortableHead>
                            <TableHead>Contacts</TableHead>
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
                        {companies.data.map((company) => (
                            <TableRow key={company.id}>
                                <TableCell>
                                    <Link
                                        href={CompanyController.show(
                                            company.id,
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {company.name}
                                    </Link>
                                    {company.domain && (
                                        <p className="text-sm text-muted-foreground">
                                            {company.domain}
                                        </p>
                                    )}
                                </TableCell>
                                <TableCell>{company.industry ?? '—'}</TableCell>
                                <TableCell>{company.contacts_count}</TableCell>
                                <TableCell>
                                    {company.owner?.name ?? '—'}
                                </TableCell>
                                <TableCell>
                                    {company.created_at
                                        ? new Date(
                                              company.created_at,
                                          ).toLocaleDateString()
                                        : '—'}
                                </TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {company.can.update && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={CompanyController.edit(
                                                        company.id,
                                                    )}
                                                    aria-label="Edit"
                                                >
                                                    <Pencil />
                                                </Link>
                                            </Button>
                                        )}
                                        {company.can.delete && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Delete"
                                                onClick={() =>
                                                    setDeleting(company)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {companies.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="text-muted-foreground"
                                >
                                    No companies found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <Pagination {...companies.meta} />
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete ${deleting?.name ?? ''}?`}
                description="The company is moved to the archive. Its contacts are kept."
                processing={processing}
                onConfirm={destroy}
            />
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [{ title: 'Companies', href: CompanyController.index() }],
};
