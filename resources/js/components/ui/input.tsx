import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const field =
    'h-9 rounded-md border border-input bg-card px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 disabled:opacity-50';

export function Input({ className, ...props }: ComponentProps<'input'>) {
    return <input className={cn(field, 'w-full min-w-0', className)} {...props} />;
}

export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return <textarea className={cn(field, 'h-auto w-full min-w-0 py-2', className)} {...props} />;
}

export function NativeSelect({ className, ...props }: ComponentProps<'select'>) {
    return <select className={cn(field, 'pr-8', className)} {...props} />;
}
