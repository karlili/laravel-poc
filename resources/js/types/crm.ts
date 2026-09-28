export type UserSummary = {
    id: number;
    name: string;
};

export type RecordAbilities = {
    update: boolean;
    delete: boolean;
};

export type Company = {
    id: number;
    name: string;
    domain: string | null;
    industry: string | null;
    email: string | null;
    phone: string | null;
    address_line1: string | null;
    address_line2: string | null;
    city: string | null;
    state: string | null;
    postcode: string | null;
    country: string | null;
    owner_id: number | null;
    owner?: UserSummary | null;
    contacts_count?: number;
    contacts?: Contact[];
    created_at: string | null;
    can: RecordAbilities;
};

export type Contact = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    company_id: number | null;
    company?: { id: number; name: string } | null;
    owner_id: number | null;
    owner?: UserSummary | null;
    created_at: string | null;
    can: RecordAbilities;
};

export type Attachment = {
    id: number;
    file_name: string;
    mime_type: string | null;
    size: string;
    url: string;
    thumb_url: string | null;
    created_at_diff: string | null;
};

export type Note = {
    id: number;
    body: string;
    author: UserSummary | null;
    created_at: string | null;
    created_at_diff: string | null;
    attachments: Attachment[];
    can: { delete: boolean };
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** A paginated API resource collection, as Laravel serialises it. */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        links: PaginationLink[];
    };
};

export type SortDirection = 'asc' | 'desc';

export type Option = { id: number; name: string };
