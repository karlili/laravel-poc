import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { TableHead } from '@/components/ui/table';
import type { SortDirection } from '@/types';

/**
 * A column header that sorts the table by `column`. Clicking the sorted
 * column flips its direction; another column starts ascending.
 */
export default function SortableHead({
    column,
    sortBy,
    sortDirection,
    onSort,
    children,
}: {
    column: string;
    sortBy: string;
    sortDirection: SortDirection;
    onSort: (column: string, direction: SortDirection) => void;
    children: ReactNode;
}) {
    const sorted = sortBy === column;
    const Icon = !sorted
        ? ArrowUpDown
        : sortDirection === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <TableHead
            aria-sort={
                sorted
                    ? sortDirection === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : undefined
            }
        >
            <button
                type="button"
                className="inline-flex items-center gap-1 hover:text-foreground"
                onClick={() =>
                    onSort(
                        column,
                        sorted && sortDirection === 'asc' ? 'desc' : 'asc',
                    )
                }
            >
                {children}
                <Icon
                    className={
                        sorted ? 'size-3.5' : 'size-3.5 text-muted-foreground'
                    }
                />
            </button>
        </TableHead>
    );
}
