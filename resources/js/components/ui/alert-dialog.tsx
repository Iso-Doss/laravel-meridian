import { AlertDialog as Primitive } from 'radix-ui';
import type { ComponentProps } from 'react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export const AlertDialog = Primitive.Root;
export const AlertDialogTrigger = Primitive.Trigger;

export function AlertDialogContent({ className, ...props }: ComponentProps<typeof Primitive.Content>) {
    return (
        <Primitive.Portal>
            <Primitive.Overlay className="fixed inset-0 z-50 bg-black/50 backdrop-blur-[1px]" />
            <Primitive.Content
                className={cn(
                    'fixed top-1/2 left-1/2 z-50 grid w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl border bg-popover p-6 text-popover-foreground shadow-lg',
                    className,
                )}
                {...props}
            />
        </Primitive.Portal>
    );
}

export function AlertDialogTitle({ className, ...props }: ComponentProps<typeof Primitive.Title>) {
    return <Primitive.Title className={cn('flex items-center gap-2 text-base font-semibold', className)} {...props} />;
}

export function AlertDialogDescription({ className, ...props }: ComponentProps<typeof Primitive.Description>) {
    return <Primitive.Description className={cn('text-sm text-muted-foreground', className)} {...props} />;
}

export function AlertDialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)} {...props} />;
}

export function AlertDialogCancel({ className, ...props }: ComponentProps<typeof Primitive.Cancel>) {
    return <Primitive.Cancel className={cn(buttonVariants({ variant: 'outline' }), className)} {...props} />;
}
