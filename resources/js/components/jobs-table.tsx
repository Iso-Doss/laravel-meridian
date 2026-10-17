import { Link } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatRelative, formatRuntime, formatSeconds } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { Job, Paginated } from '@/types';

export function Tags({ tags, limit = 4 }: { tags: string[]; limit?: number }) {
    return (
        <span className="flex flex-wrap gap-1">
            {tags.slice(0, limit).map((tag) => (
                <span key={tag} className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                    {tag}
                </span>
            ))}
        </span>
    );
}

export function JobsTable({ jobs }: { jobs: Paginated<Job> }) {
    const { t, locale } = useTranslation();

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
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {jobs.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
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
                            </TableCell>
                            <TableCell className="text-right tabular-nums">{job.attempts}</TableCell>
                            <TableCell title={formatDateTime(job.queued_at, locale)}>{formatRelative(job.queued_at, locale)}</TableCell>
                            <TableCell className="text-right tabular-nums">{formatSeconds(job.wait)}</TableCell>
                            <TableCell className="text-right tabular-nums">{formatRuntime(job.runtime, locale)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination paginator={jobs} />
        </>
    );
}
