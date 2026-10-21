import { DropdownMenu as Primitive } from 'radix-ui';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export const DropdownMenu = Primitive.Root;
export const DropdownMenuTrigger = Primitive.Trigger;
export const DropdownMenuRadioGroup = Primitive.RadioGroup;

export function DropdownMenuContent({ className, sideOffset = 6, ...props }: ComponentProps<typeof Primitive.Content>) {
    return (
        <Primitive.Portal>
            <Primitive.Content
                sideOffset={sideOffset}
                className={cn('z-50 min-w-40 rounded-lg border bg-popover p-1 text-popover-foreground shadow-md', className)}
                {...props}
            />
        </Primitive.Portal>
    );
}

export function DropdownMenuLabel({ className, ...props }: ComponentProps<typeof Primitive.Label>) {
    return <Primitive.Label className={cn('px-2 py-1.5 text-xs font-medium text-muted-foreground', className)} {...props} />;
}

export function DropdownMenuRadioItem({ className, children, ...props }: ComponentProps<typeof Primitive.RadioItem>) {
    return (
        <Primitive.RadioItem
            className={cn(
                'relative flex cursor-pointer items-center gap-2 rounded-md py-1.5 pe-2 ps-7 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground [&_svg]:size-4',
                className,
            )}
            {...props}
        >
            <span className="absolute start-2 flex size-3.5 items-center justify-center">
                <Primitive.ItemIndicator>
                    <span className="block size-1.5 rounded-full bg-current" />
                </Primitive.ItemIndicator>
            </span>
            {children}
        </Primitive.RadioItem>
    );
}

export function DropdownMenuItem({ className, ...props }: ComponentProps<typeof Primitive.Item>) {
    return (
        <Primitive.Item
            className={cn(
                'relative flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground [&_svg]:size-4',
                className,
            )}
            {...props}
        />
    );
}
