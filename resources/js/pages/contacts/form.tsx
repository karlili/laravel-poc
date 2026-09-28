import { Form, Head, Link } from '@inertiajs/react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import TextField from '@/components/text-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type { BreadcrumbItem, Contact, Option } from '@/types';

type Props = {
    contact: Contact | null;
    companyId: number | null;
    companies: Option[];
    canAssign: boolean;
    users: Option[];
};

export default function ContactForm({
    contact,
    companyId,
    companies,
    canAssign,
    users,
}: Props) {
    const title = contact ? 'Edit contact' : 'New contact';
    const action = contact
        ? ContactController.update.form(contact.id)
        : ContactController.store.form();

    return (
        <>
            <Head title={title} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <Heading title={title} />

                <Form {...action} className="flex flex-col gap-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 md:grid-cols-2">
                                <TextField
                                    label="First name"
                                    name="first_name"
                                    defaultValue={contact?.first_name}
                                    error={errors.first_name}
                                    required
                                    autoFocus
                                />
                                <TextField
                                    label="Last name"
                                    name="last_name"
                                    defaultValue={contact?.last_name}
                                    error={errors.last_name}
                                    required
                                />
                                <TextField
                                    label="Email"
                                    name="email"
                                    type="email"
                                    defaultValue={contact?.email ?? ''}
                                    error={errors.email}
                                />
                                <TextField
                                    label="Phone"
                                    name="phone"
                                    type="tel"
                                    defaultValue={contact?.phone ?? ''}
                                    error={errors.phone}
                                />
                                <TextField
                                    label="Job title"
                                    name="job_title"
                                    defaultValue={contact?.job_title ?? ''}
                                    error={errors.job_title}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="company_id">Company</Label>
                                    <NativeSelect
                                        id="company_id"
                                        name="company_id"
                                        defaultValue={companyId ?? ''}
                                    >
                                        <option value="">No company</option>
                                        {companies.map((company) => (
                                            <option
                                                key={company.id}
                                                value={company.id}
                                            >
                                                {company.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <InputError message={errors.company_id} />
                                </div>
                            </div>

                            {canAssign && (
                                <div className="grid gap-2">
                                    <Label htmlFor="owner_id">Owner</Label>
                                    <NativeSelect
                                        id="owner_id"
                                        name="owner_id"
                                        defaultValue={contact?.owner_id ?? ''}
                                    >
                                        <option value="">Unassigned</option>
                                        {users.map((user) => (
                                            <option
                                                key={user.id}
                                                value={user.id}
                                            >
                                                {user.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <InputError message={errors.owner_id} />
                                </div>
                            )}

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-contact-button"
                                >
                                    Save
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={
                                            contact
                                                ? ContactController.show(
                                                      contact.id,
                                                  )
                                                : ContactController.index()
                                        }
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ContactForm.layout = ({ contact }: Props) => {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Contacts', href: ContactController.index() },
    ];

    if (contact) {
        breadcrumbs.push(
            {
                title: contact.full_name,
                href: ContactController.show(contact.id),
            },
            { title: 'Edit', href: ContactController.edit(contact.id) },
        );
    } else {
        breadcrumbs.push({ title: 'New', href: ContactController.create() });
    }

    return { breadcrumbs };
};
