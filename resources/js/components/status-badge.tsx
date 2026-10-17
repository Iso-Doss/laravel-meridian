import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/lib/i18n';

const VARIANTS = {
    pending: 'info',
    delayed: 'secondary',
    processing: 'warning',
    working: 'warning',
    paused: 'warning',
    completed: 'success',
    running: 'success',
    finished: 'success',
    idle: 'outline',
    silenced: 'secondary',
    failed: 'destructive',
    cancelled: 'destructive',
    inactive: 'destructive',
} as const;

const LABELS: Record<keyof typeof VARIANTS, string> = {
    pending: 'Pending',
    delayed: 'Delayed',
    processing: 'Processing',
    working: 'Working',
    paused: 'Paused',
    completed: 'Completed',
    running: 'Running',
    finished: 'Finished',
    idle: 'Idle',
    silenced: 'Silenced',
    failed: 'Failed',
    cancelled: 'Cancelled',
    inactive: 'Inactive',
};

export function StatusBadge({ status }: { status: string }) {
    const { t } = useTranslation();
    const key = (status in VARIANTS ? status : 'idle') as keyof typeof VARIANTS;

    return (
        <Badge variant={VARIANTS[key]}>
            <span className="size-1.5 rounded-full bg-current" aria-hidden />
            {t(LABELS[key] ?? status)}
        </Badge>
    );
}
