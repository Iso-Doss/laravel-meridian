import { router } from '@inertiajs/react';
import { Loader2, type LucideIcon } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';

/** A button that asks for confirmation, then sends the request through Inertia. */
export function ConfirmAction({
    url,
    method,
    icon: Icon,
    label,
    title,
    description,
    confirmLabel,
    tone = 'default',
    data,
    compact = false,
}: {
    url: string;
    method: 'post' | 'delete';
    icon: LucideIcon;
    label: ReactNode;
    title: string;
    description: string;
    confirmLabel: string;
    tone?: 'default' | 'destructive';
    /** Sent with the request (e.g. the filters of a list). */
    data?: Record<string, string>;
    /** A small outline button, for table rows. */
    compact?: boolean;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        router.visit(url, {
            method,
            data,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <Button
                    variant={compact || tone === 'destructive' ? 'outline' : 'default'}
                    size={compact ? 'sm' : 'default'}
                    className={tone === 'destructive' ? 'text-destructive' : undefined}
                >
                    <Icon /> {label}
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogTitle>
                    <Icon className={tone === 'destructive' ? 'size-5 text-destructive' : 'size-5 text-primary'} />
                    {title}
                </AlertDialogTitle>
                <AlertDialogDescription>{description}</AlertDialogDescription>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>{t('Cancel')}</AlertDialogCancel>
                    <Button variant={tone === 'destructive' ? 'destructive' : 'default'} onClick={confirm} disabled={processing}>
                        {processing && <Loader2 className="animate-spin" />}
                        {confirmLabel}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
