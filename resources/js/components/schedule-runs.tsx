import { useTranslation } from '@/lib/i18n';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ScheduleRun } from '@/types';

const COLORS: Record<ScheduleRun['status'], string> = {
    success: 'bg-success',
    failed: 'bg-destructive',
    running: 'bg-warning',
    skipped: 'bg-muted-foreground/40',
};

/** The last runs of a task, the oldest first, as colored dots. */
export function RecentRuns({ runs }: { runs: ScheduleRun[] }) {
    const { t, locale } = useTranslation();

    return (
        <span className="flex items-center gap-1">
            {[...runs].reverse().map((run, index) => (
                <span
                    key={index}
                    className={cn('size-2.5 rounded-full', COLORS[run.status])}
                    title={`${formatDateTime(run.started_at, locale)} — ${t(run.status === 'success' ? 'Succeeded' : run.status.charAt(0).toUpperCase() + run.status.slice(1))}`}
                />
            ))}
        </span>
    );
}
