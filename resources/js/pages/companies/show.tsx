import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import AttachmentController from '@/actions/App/Http/Controllers/AttachmentController';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import NoteController from '@/actions/App/Http/Controllers/NoteController';
import Attachments from '@/components/attachments';
import Heading from '@/components/heading';
import NotesThread from '@/components/notes-thread';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Attachment, Company, Note } from '@/types';

type Props = {
    company: Company;
    notes: Note[];
    attachments: Attachment[];
    maxUploadMb: number;
    can: {
        createContact: boolean;
        createNote: boolean;
        manageAttachments: boolean;
    };
};

export default function CompanyShow({
    company,
    notes,
    attachments,
    maxUploadMb,
    can,
}: Props) {
    const address = [
        company.address_line1,
        company.address_line2,
        company.city,
        company.state,
        company.postcode,
        company.country,
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <>
            <Head title={company.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={company.name}
                        description={[company.industry, company.domain]
                            .filter(Boolean)
                            .join(' · ')}
                    />

                    {company.can.update && (
                        <Button variant="outline" asChild>
                            <Link href={CompanyController.edit(company.id)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Email
                                        </dt>
                                        <dd>{company.email ?? '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Phone
                                        </dt>
                                        <dd>{company.phone ?? '—'}</dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-muted-foreground">
                                            Address
                                        </dt>
                                        <dd>{address || '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Owner
                                        </dt>
                                        <dd>
                                            {company.owner?.name ??
                                                'Unassigned'}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Contacts</CardTitle>
                                {can.createContact && (
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={ContactController.create({
                                                query: { company: company.id },
                                            })}
                                        >
                                            <Plus />
                                            Add contact
                                        </Link>
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {(company.contacts ?? []).map((contact) => (
                                    <div
                                        key={contact.id}
                                        className="flex items-center justify-between border-b py-2 last:border-0"
                                    >
                                        <div>
                                            <Link
                                                href={ContactController.show(
                                                    contact.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {contact.full_name}
                                            </Link>
                                            <p className="text-sm text-muted-foreground">
                                                {contact.job_title}
                                            </p>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {contact.email}
                                        </p>
                                    </div>
                                ))}
                                {(company.contacts ?? []).length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No contacts yet.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <NotesThread
                            notes={notes}
                            storeUrl={
                                can.createNote
                                    ? NoteController.storeForCompany.url(
                                          company.id,
                                      )
                                    : null
                            }
                        />
                    </div>

                    <div>
                        <Attachments
                            attachments={attachments}
                            maxSizeMb={maxUploadMb}
                            storeUrl={
                                can.manageAttachments
                                    ? AttachmentController.storeForCompany.url(
                                          company.id,
                                      )
                                    : null
                            }
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

CompanyShow.layout = ({ company }: Props) => ({
    breadcrumbs: [
        { title: 'Companies', href: CompanyController.index() },
        { title: company.name, href: CompanyController.show(company.id) },
    ],
});
