import { Head, Link, usePage } from '@inertiajs/react';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type RecentNote = {
    id: number;
    body: string;
    author: string | null;
    created_at_diff: string | null;
    notable: { type: 'company' | 'contact'; id: number; name: string } | null;
};

export default function Dashboard({
    stats,
    recentNotes,
}: {
    stats: { companies: number; contacts: number; mine: number };
    recentNotes: RecentNote[];
}) {
    const { auth } = usePage().props;

    const tiles = [
        { label: 'Companies', value: stats.companies },
        { label: 'Contacts', value: stats.contacts },
        { label: 'Records assigned to you', value: stats.mine },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <Heading title={`Welcome back, ${auth.user.name}`} />

                <div className="grid gap-4 md:grid-cols-3">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {tile.label}
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {tile.value.toLocaleString()}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent notes</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {recentNotes.map((note) => (
                            <div
                                key={note.id}
                                className="border-t pt-3 first:border-0 first:pt-0"
                            >
                                <p className="text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {note.author ?? 'Former user'}
                                    </span>
                                    {note.notable && (
                                        <>
                                            {' on '}
                                            <Link
                                                href={
                                                    note.notable.type ===
                                                    'company'
                                                        ? CompanyController.show(
                                                              note.notable.id,
                                                          )
                                                        : ContactController.show(
                                                              note.notable.id,
                                                          )
                                                }
                                                className="text-foreground hover:underline"
                                            >
                                                {note.notable.name}
                                            </Link>
                                        </>
                                    )}{' '}
                                    · {note.created_at_diff}
                                </p>
                                <p className="mt-1 line-clamp-2 text-sm">
                                    {note.body}
                                </p>
                            </div>
                        ))}

                        {recentNotes.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No notes yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
