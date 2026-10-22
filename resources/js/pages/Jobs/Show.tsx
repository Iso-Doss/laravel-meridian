import { Link, usePage } from '@inertiajs/react';
import { PencilLine, RotateCcw, Trash2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import { EditJobData } from '@/components/edit-job-data';
import { JobProgress } from '@/components/job-progress';
import { Tags } from '@/components/jobs-table';
import { Layout } from '@/components/layout';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime, formatRuntime, formatSeconds } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { JobDetails, SharedProps } from '@/types';

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
    const [editing, setEditing] = useState(false);
    // No refresh while the data is being edited: it would reset the form.
    useAutoRefresh(['job'], !editing);

    const pending = job.status === 'pending';
    const running = job.status === 'processing';
    const { meridian } = usePage<SharedProps>().props;

    return (
        <Layout
            title={job.short_name}
            badge={
                <>
                    <StatusBadge status={job.delayed ? 'delayed' : job.status} />
                    {job.cancelling && <StatusBadge status="cancelling" />}
                </>
            }
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
                            label={pending || running ? t('Cancel job') : t('Delete')}
                            title={pending || running ? t('Cancel this job?') : t('Delete this job?')}
                            description={
                                running
                                    ? t('The job is asked to stop. If it does not stop by itself within :seconds seconds, Meridian stops the worker running it (when the worker runs under php artisan meridian).', {
                                          seconds: meridian.cancel_timeout,
                                      })
                                    : pending
                                      ? t('The job will be removed from the queue and will never run.')
                                      : t('The record of this job will be deleted from Meridian.')
                            }
                            confirmLabel={pending || running ? t('Cancel the job') : t('Delete the job')}
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
                    {job.origin && (
                        <Row label={t('Dispatched by')}>
                            <span className="me-2 rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                {{ http: t('HTTP request'), console: t('Command'), schedule: t('Scheduled task'), job: t('Job') }[job.origin.type]}
                            </span>
                            {job.origin.parent ? (
                                <Link href={job.origin.parent.url} className="font-mono text-primary hover:underline">
                                    {job.origin.label}
                                </Link>
                            ) : (
                                <span className="font-mono">{job.origin.label}</span>
                            )}
                            {job.origin.parent?.status && (
                                <span className="ms-2 inline-block align-middle">
                                    <StatusBadge status={job.origin.parent.status} />
                                </span>
                            )}
                            {job.origin.user && <span className="text-muted-foreground"> · {job.origin.user}</span>}
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
                    {job.progress !== null && (
                        <Row label={t('Progress')}>
                            <JobProgress job={job} detailed />
                        </Row>
                    )}
                </dl>
            </Card>

            {job.children.count > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>{t('Dispatched jobs')}</CardTitle>
                        <CardDescription>{t(':count job(s) dispatched by this job', { count: job.children.count })}</CardDescription>
                    </CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Job')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Queued at')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {job.children.jobs.map((child) => (
                                <TableRow key={child.uuid}>
                                    <TableCell>
                                        <Link href={child.url} className="font-mono text-primary hover:underline">
                                            {child.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={child.status} />
                                    </TableCell>
                                    <TableCell>{formatDateTime(child.queued_at, locale)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            {job.exception && (
                <Card className="mt-6 border-destructive/30">
                    <CardHeader>
                        <CardTitle className="text-destructive">{t('Exception')}</CardTitle>
                        {job.error_group && job.error_group.count > 1 && (
                            <Link href={job.error_group.url} className="text-sm text-primary hover:underline">
                                {t(':count failed jobs with the same error', { count: job.error_group.count })}
                            </Link>
                        )}
                    </CardHeader>
                    <CardContent>
                        <pre className="max-h-[28rem] overflow-auto rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed break-words whitespace-pre-wrap">
                            {job.exception}
                        </pre>
                    </CardContent>
                </Card>
            )}

            {job.retry_history.length > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>{t('Retries')}</CardTitle>
                        <CardDescription>{t(':count manual retry(ies)', { count: job.retry_history.length })}</CardDescription>
                    </CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>#</TableHead>
                                <TableHead>{t('Retried at')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Finished at')}</TableHead>
                                <TableHead className="text-end">{t('Runtime')}</TableHead>
                                <TableHead>{t('Exception')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {job.retry_history.map((attempt, index) => (
                                <TableRow key={index}>
                                    <TableCell className="tabular-nums">{index + 1}</TableCell>
                                    <TableCell>{formatDateTime(attempt.retried_at, locale)}</TableCell>
                                    <TableCell>
                                        <StatusBadge status={attempt.status} />
                                    </TableCell>
                                    <TableCell>{formatDateTime(attempt.finished_at, locale)}</TableCell>
                                    <TableCell className="text-end tabular-nums">{formatRuntime(attempt.runtime, locale)}</TableCell>
                                    <TableCell className="max-w-md truncate font-mono text-xs" title={attempt.exception ?? undefined}>
                                        {attempt.exception ? attempt.exception.split('\n')[0] : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            {job.data && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>{t('Data')}</CardTitle>
                        {job.edit && !editing && (
                            <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                                <PencilLine /> {t('Edit and retry')}
                            </Button>
                        )}
                    </CardHeader>
                    {job.edit && editing ? (
                        <EditJobData url={job.edit.url} fields={job.edit.fields} onDone={() => setEditing(false)} />
                    ) : job.data.error ? (
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                {t('The data of this job cannot be read: :error', { error: job.data.error })}
                            </p>
                        </CardContent>
                    ) : job.data.properties.length === 0 ? (
                        <CardContent>
                            <p className="text-sm text-muted-foreground">{t('This job has no data.')}</p>
                        </CardContent>
                    ) : (
                        <dl>
                            {job.data.properties.map((property) => (
                                <Row key={property.name} label={property.name}>
                                    <span className="font-mono text-xs break-all">{property.value}</span>
                                </Row>
                            ))}
                        </dl>
                    )}
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
