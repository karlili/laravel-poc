import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A labelled input for Inertia's <Form>, which reads the value by `name`.
 */
export default function TextField({
    label,
    name,
    error,
    className,
    ...props
}: ComponentProps<typeof Input> & {
    label: string;
    name: string;
    error?: string;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                aria-invalid={error ? true : undefined}
                {...props}
            />
            <InputError message={error} />
        </div>
    );
}
