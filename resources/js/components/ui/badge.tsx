import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export const badgeVariants = cva(
    'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-primary/10 text-primary',
                secondary: 'border-transparent bg-secondary text-secondary-foreground',
                outline: 'text-muted-foreground',
                success: 'border-transparent bg-success/15 text-success',
                warning: 'border-transparent bg-warning/15 text-warning',
                destructive: 'border-transparent bg-destructive/15 text-destructive',
                info: 'border-transparent bg-info/15 text-info',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

export function Badge({ className, variant, ...props }: ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}
