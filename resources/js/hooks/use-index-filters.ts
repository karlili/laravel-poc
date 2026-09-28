import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type FilterValue = string | boolean;

/**
 * Keeps an index page's filters in the query string, like Livewire's #[Url]:
 * each change reloads the page's props without adding a history entry and
 * goes back to page 1.
 */
export function useIndexFilters<T extends Record<string, FilterValue>>(
    url: string,
    initial: T,
    defaults: T,
) {
    const [filters, setFilters] = useState<T>(initial);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(timer.current), []);

    const visit = (next: T) => {
        // Leave defaults out of the URL, as Livewire's `except` did.
        const query = Object.fromEntries(
            Object.entries(next)
                .filter(([key, value]) => value !== defaults[key])
                .map(([key, value]) => [
                    key,
                    typeof value === 'boolean' ? (value ? '1' : '0') : value,
                ]),
        );

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const update = (changes: Partial<T>, { debounce = 0 } = {}) => {
        const next = { ...filters, ...changes };
        setFilters(next);
        clearTimeout(timer.current);

        if (debounce > 0) {
            timer.current = setTimeout(() => visit(next), debounce);
        } else {
            visit(next);
        }
    };

    return { filters, update };
}
