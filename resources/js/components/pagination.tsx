import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types';

/**
 * Page links for a Laravel paginator. Keeps scroll and filter state between pages.
 */
export default function Pagination({
    links,
    from,
    to,
    total,
}: {
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}) {
    if (links.length <= 3) {
        return total > 0 ? (
            <p className="text-sm text-muted-foreground">
                Showing {from} to {to} of {total}
            </p>
        ) : null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                Showing {from} to {to} of {total}
            </p>
            <nav aria-label="Pagination" className="flex flex-wrap gap-1">
                {links.map((link, index) => {
                    // Laravel labels the ends "&laquo; Previous" / "Next &raquo;".
                    const label = link.label
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›');
                    const className = cn(
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input hover:bg-accent',
                        !link.url && 'pointer-events-none opacity-50',
                    );

                    return link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={className}
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span key={index} className={className}>
                            {label}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}
