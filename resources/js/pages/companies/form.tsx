import { Form, Head, Link } from '@inertiajs/react';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import TextField from '@/components/text-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type { BreadcrumbItem, Company, Option } from '@/types';

type Props = {
    company: Company | null;
    canAssign: boolean;
    users: Option[];
};

export default function CompanyForm({ company, canAssign, users }: Props) {
    const title = company ? 'Edit company' : 'New company';
    const action = company
        ? CompanyController.update.form(company.id)
        : CompanyController.store.form();

    return (
        <>
            <Head title={title} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <Heading title={title} />

                <Form {...action} className="flex flex-col gap-6">
                    {({ processing, errors }) => (
                        <>
                            <TextField
                                label="Name"
                                name="name"
                                defaultValue={company?.name}
                                error={errors.name}
                                required
                                autoFocus
                            />

                            <div className="grid gap-6 md:grid-cols-2">
                                <TextField
                                    label="Website domain"
                                    name="domain"
                                    defaultValue={company?.domain ?? ''}
                                    error={errors.domain}
                                    placeholder="example.com"
                                />
                                <TextField
                                    label="Industry"
                                    name="industry"
                                    defaultValue={company?.industry ?? ''}
                                    error={errors.industry}
                                />
                                <TextField
                                    label="Email"
                                    name="email"
                                    type="email"
                                    defaultValue={company?.email ?? ''}
                                    error={errors.email}
                                />
                                <TextField
                                    label="Phone"
                                    name="phone"
                                    type="tel"
                                    defaultValue={company?.phone ?? ''}
                                    error={errors.phone}
                                />
                            </div>

                            <fieldset className="grid gap-4">
                                <legend className="mb-4 text-base font-medium">
                                    Address
                                </legend>
                                <div className="grid gap-6 md:grid-cols-2">
                                    <TextField
                                        label="Address line 1"
                                        name="address_line1"
                                        defaultValue={
                                            company?.address_line1 ?? ''
                                        }
                                        error={errors.address_line1}
                                        className="md:col-span-2"
                                    />
                                    <TextField
                                        label="Address line 2"
                                        name="address_line2"
                                        defaultValue={
                                            company?.address_line2 ?? ''
                                        }
                                        error={errors.address_line2}
                                        className="md:col-span-2"
                                    />
                                    <TextField
                                        label="City"
                                        name="city"
                                        defaultValue={company?.city ?? ''}
                                        error={errors.city}
                                    />
                                    <TextField
                                        label="State / region"
                                        name="state"
                                        defaultValue={company?.state ?? ''}
                                        error={errors.state}
                                    />
                                    <TextField
                                        label="Postcode"
                                        name="postcode"
                                        defaultValue={company?.postcode ?? ''}
                                        error={errors.postcode}
                                    />
                                    <TextField
                                        label="Country code"
                                        name="country"
                                        defaultValue={company?.country ?? ''}
                                        error={errors.country}
                                        placeholder="AU"
                                        maxLength={2}
                                    />
                                </div>
                            </fieldset>

                            {canAssign && (
                                <div className="grid gap-2">
                                    <Label htmlFor="owner_id">Owner</Label>
                                    <NativeSelect
                                        id="owner_id"
                                        name="owner_id"
                                        defaultValue={company?.owner_id ?? ''}
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
                                    data-test="save-company-button"
                                >
                                    Save
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={
                                            company
                                                ? CompanyController.show(
                                                      company.id,
                                                  )
                                                : CompanyController.index()
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

CompanyForm.layout = ({ company }: Props) => {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Companies', href: CompanyController.index() },
    ];

    if (company) {
        breadcrumbs.push(
            { title: company.name, href: CompanyController.show(company.id) },
            { title: 'Edit', href: CompanyController.edit(company.id) },
        );
    } else {
        breadcrumbs.push({ title: 'New', href: CompanyController.create() });
    }

    return { breadcrumbs };
};
