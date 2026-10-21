import { formatSeconds } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { Job } from '@/types';

/** Progress reported by a running job, with its estimated remaining time. */
export function JobProgress({ job, detailed = false }: { job: Pick<Job, 'progress' | 'progress_message' | 'remaining'>; detailed?: boolean }) {
    const { t } = useTranslation();

    if (job.progress === null) {
        return null;
    }

    return (
        <span className="flex flex-wrap items-center gap-2" title={job.progress_message ?? undefined}>
            <span
                className="flex h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={job.progress}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <span className="h-full bg-primary transition-[width]" style={{ width: `${job.progress}%` }} />
            </span>
            <span className="text-xs text-muted-foreground tabular-nums">
                {job.progress}%
                {job.remaining !== null && ` · ${t('about :time left', { time: formatSeconds(job.remaining) })}`}
            </span>
            {detailed && job.progress_message && <span className="w-full text-xs text-muted-foreground">{job.progress_message}</span>}
        </span>
    );
}
