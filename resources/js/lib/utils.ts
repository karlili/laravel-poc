import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * The first error for a field or any of its items, e.g. "uploads" or "uploads.2".
 */
export function firstError(
    errors: Partial<Record<string, string>>,
    field: string,
): string | undefined {
    return (
        errors[field] ??
        Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1]
    );
}
