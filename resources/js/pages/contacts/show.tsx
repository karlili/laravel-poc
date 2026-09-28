import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import AttachmentController from '@/actions/App/Http/Controllers/AttachmentController';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import NoteController from '@/actions/App/Http/Controllers/NoteController';
import Attachments from '@/components/attachments';
import NotesThread from '@/components/notes-thread';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Attachment, Contact, Note } from '@/types';

type Props = {
    contact: Contact;
    notes: Note[];
    attachments: Attachment[];
    maxUploadMb: number;
    can: {
        createNote: boolean;
        manageAttachments: boolean;
    };
};

export default function ContactShow({
    contact,
    notes,
    attachments,
    maxUploadMb,
    can,
}: Props) {
    return (
        <>
            <Head title={contact.full_name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            {contact.full_name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {contact.job_title}
                            {contact.job_title && contact.company && ' · '}
                            {contact.company && (
                                <Link
                                    href={CompanyController.show(
                                        contact.company.id,
                                    )}
                                    className="text-foreground hover:underline"
                                >
                                    {contact.company.name}
                                </Link>
                            )}
                        </p>
                    </div>

                    {contact.can.update && (
                        <Button variant="outline" asChild>
                            <Link href={ContactController.edit(contact.id)}>
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
                                        <dd>
                                            {contact.email ? (
                                                <a
                                                    href={`mailto:${contact.email}`}
                                                    className="hover:underline"
                                                >
                                                    {contact.email}
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Phone
                                        </dt>
                                        <dd>{contact.phone ?? '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Owner
                                        </dt>
                                        <dd>
                                            {contact.owner?.name ??
                                                'Unassigned'}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>

                        <NotesThread
                            notes={notes}
                            storeUrl={
                                can.createNote
                                    ? NoteController.storeForContact.url(
                                          contact.id,
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
                                    ? AttachmentController.storeForContact.url(
                                          contact.id,
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

ContactShow.layout = ({ contact }: Props) => ({
    breadcrumbs: [
        { title: 'Contacts', href: ContactController.index() },
        { title: contact.full_name, href: ContactController.show(contact.id) },
    ],
});
