import { Link } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import { Tags } from '@/components/jobs-table';
import { Layout } from '@/components/layout';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime, formatRuntime, formatSeconds } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { JobDetails } from '@/types';

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 border-b px-5 py-3 last:border-0 sm:grid-cols-[11rem_1fr]">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-sm break-words">{children}</dd>
        </div>
    );
}

export default function JobShow({ job }: { job: JobDetails }) {
    const { t, locale } = useTranslation();
    useAutoRefresh(['job']);

    const pending = job.status === 'pending';

    return (
        <Layout
            title={job.short_name}
            badge={<StatusBadge status={job.delayed ? 'delayed' : job.status} />}
            actions={
                <>
                    {job.retry_url && (
                        <ConfirmAction
                            url={job.retry_url}
                            method="post"
                            icon={RotateCcw}
                            label={t('Retry')}
                            title={t('Retry this job?')}
                            description={t('The job will be pushed back onto the :queue queue.', { queue: job.queue })}
                            confirmLabel={t('Retry the job')}
                        />
                    )}
                    {job.delete_url && (
                        <ConfirmAction
                            url={job.delete_url}
                            method="delete"
                            icon={Trash2}
                            tone="destructive"
                            label={pending ? t('Cancel job') : t('Delete')}
                            title={pending ? t('Cancel this job?') : t('Delete this job?')}
                            description={
                                pending
                                    ? t('The job will be removed from the queue and will never run.')
                                    : t('The record of this job will be deleted from Meridian.')
                            }
                            confirmLabel={pending ? t('Cancel the job') : t('Delete the job')}
                        />
                    )}
                </>
            }
        >
            <Card>
                <dl>
                    <Row label={t('Class')}>
                        <span className="font-mono">{job.name}</span>
                    </Row>
                    <Row label="UUID">
                        <span className="font-mono">{job.uuid}</span>
                    </Row>
                    <Row label={t('Queue')}>
                        {job.connection}:{job.queue}
                        {job.job_id && <span className="text-muted-foreground"> (jobs.id {job.job_id})</span>}
                    </Row>
                    <Row label={t('Tags')}>{job.tags.length ? <Tags tags={job.tags} limit={50} /> : <span className="text-muted-foreground">—</span>}</Row>
                    {job.batch_url && (
                        <Row label={t('Batch')}>
                            <Link href={job.batch_url} className="font-mono text-primary hover:underline">
                                {job.batch_id}
                            </Link>
                        </Row>
                    )}
                    <Row label={t('Attempts')}>
                        {job.attempts}
                        {job.retries > 0 && <span className="text-muted-foreground"> · {t('retried manually :count time(s)', { count: job.retries })}</span>}
                    </Row>
                    <Row label={t('Queued at')}>{formatDateTime(job.queued_at, locale)}</Row>
                    {job.available_at !== job.queued_at && <Row label={t('Available at')}>{formatDateTime(job.available_at, locale)}</Row>}
                    <Row label={t('Started at')}>
                        {formatDateTime(job.reserved_at, locale)}
                        {job.worker && <span className="text-muted-foreground"> · {t('by :worker', { worker: job.worker })}</span>}
                    </Row>
                    {job.completed_at && <Row label={t('Completed at')}>{formatDateTime(job.completed_at, locale)}</Row>}
                    {job.failed_at && <Row label={t('Failed at')}>{formatDateTime(job.failed_at, locale)}</Row>}
                    <Row label={t('Wait')}>{formatSeconds(job.wait)}</Row>
                    <Row label={t('Runtime')}>{formatRuntime(job.runtime, locale)}</Row>
                </dl>
            </Card>

            {job.exception && (
                <Card className="mt-6 border-destructive/30">
                    <CardHeader>
                        <CardTitle className="text-destructive">{t('Exception')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="max-h-[28rem] overflow-auto rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed break-words whitespace-pre-wrap">
                            {job.exception}
                        </pre>
                    </CardContent>
                </Card>
            )}

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>{t('Payload')}</CardTitle>
                </CardHeader>
                <CardContent>
                    {job.payload ? (
                        <pre className="max-h-[28rem] overflow-auto rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed break-words whitespace-pre-wrap">
                            {job.payload}
                        </pre>
                    ) : (
                        <p className="text-sm text-muted-foreground">{t('Payload not stored (see meridian.store_payload).')}</p>
                    )}
                </CardContent>
            </Card>
        </Layout>
    );
}
