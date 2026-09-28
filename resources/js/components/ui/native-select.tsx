import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * A plain <select> styled like Input. Unlike the Radix Select it allows an
 * empty option ("All", "Unassigned") and submits with Inertia's <Form>.
 */
function NativeSelect({ className, ...props }: React.ComponentProps<'select'>) {
    return (
        <select
            data-slot="native-select"
            className={cn(
                'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                '[&_option]:bg-popover [&_option]:text-popover-foreground',
                className,
            )}
            {...props}
        />
    );
}

export { NativeSelect };
