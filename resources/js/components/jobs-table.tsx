import { Link, usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { ConfirmAction } from '@/components/confirm-action';
import { JobProgress } from '@/components/job-progress';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatRelative, formatRuntime, formatSeconds } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Job, Paginated, SharedProps } from '@/types';

/** Tags of a job; each one opens the jobs carrying it. */
export function Tags({ tags, limit = 4 }: { tags: string[]; limit?: number }) {
    const { nav } = usePage<SharedProps>().props;

    return (
        <span className="flex flex-wrap gap-1">
            {tags.slice(0, limit).map((tag) => (
                <Link
                    key={tag}
                    href={nav.jobs.all}
                    data={{ tag }}
                    className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    {tag}
                </Link>
            ))}
        </span>
    );
}

/** The actions column stays visible when the table scrolls horizontally (small screens). */
const STICKY = 'sticky right-0 z-10 shadow-[-8px_0_8px_-8px_rgb(0_0_0/0.15)]';

export function JobsTable({ jobs }: { jobs: Paginated<Job> }) {
    const { t, locale } = useTranslation();
    const retryable = jobs.data.some((job) => job.retry_url);
    const columns = retryable ? 8 : 7;

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('Job')}</TableHead>
                        <TableHead>{t('Queue')}</TableHead>
                        <TableHead>{t('Status')}</TableHead>
                        <TableHead className="text-right">{t('Attempts')}</TableHead>
                        <TableHead>{t('Queued')}</TableHead>
                        <TableHead className="text-right">{t('Wait')}</TableHead>
                        <TableHead className="text-right">{t('Runtime')}</TableHead>
                        {retryable && <TableHead className={cn(STICKY, 'bg-muted text-right')}>{t('Actions')}</TableHead>}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {jobs.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={columns} className="py-10 text-center text-muted-foreground">
                                {t('No jobs to show.')}
                            </TableCell>
                        </TableRow>
                    )}
                    {jobs.data.map((job) => (
                        <TableRow key={job.uuid}>
                            <TableCell className="whitespace-normal">
                                <Link href={job.url} className="font-medium text-primary hover:underline" title={job.name}>
                                    {job.short_name}
                                </Link>
                                {job.tags.length > 0 && (
                                    <div className="mt-1">
                                        <Tags tags={job.tags} />
                                    </div>
                                )}
                            </TableCell>
                            <TableCell>
                                <span className="text-muted-foreground">{job.connection}:</span>
                                {job.queue}
                            </TableCell>
                            <TableCell>
                                <span className="flex items-center gap-2">
                                    <StatusBadge status={job.delayed ? 'delayed' : job.status} />
                                    {job.retries > 0 && (
                                        <span className="flex items-center gap-0.5 text-xs text-muted-foreground" title={t('Manual retries')}>
                                            <RotateCcw className="size-3" />
                                            {job.retries}
                                        </span>
                                    )}
                                </span>
                                {job.progress !== null && (
                                    <div className="mt-1.5">
                                        <JobProgress job={job} />
                                    </div>
                                )}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">{job.attempts}</TableCell>
                            <TableCell title={formatDateTime(job.queued_at, locale)}>{formatRelative(job.queued_at, locale)}</TableCell>
                            <TableCell className="text-right tabular-nums">{formatSeconds(job.wait)}</TableCell>
                            <TableCell className="text-right tabular-nums">{formatRuntime(job.runtime, locale)}</TableCell>
                            {retryable && (
                                <TableCell className={cn(STICKY, 'bg-card text-right')}>
                                    {job.retry_url && (
                                        <ConfirmAction
                                            compact
                                            url={job.retry_url}
                                            method="post"
                                            icon={RotateCcw}
                                            label={t('Retry')}
                                            title={t('Retry this job?')}
                                            description={t('The job will be pushed back onto the :queue queue.', { queue: job.queue })}
                                            confirmLabel={t('Retry the job')}
                                        />
                                    )}
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination paginator={jobs} />
        </>
    );
}
