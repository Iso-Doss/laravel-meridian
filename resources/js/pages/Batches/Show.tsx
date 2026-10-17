import { RotateCcw } from 'lucide-react';
import type { ReactNode } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import { JobsTable } from '@/components/jobs-table';
import { Layout } from '@/components/layout';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { BATCH_STATUS, BatchProgress } from '@/pages/Batches/Index';
import type { Batch, Job, Paginated } from '@/types';

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 border-b px-5 py-3 last:border-0 sm:grid-cols-[11rem_1fr]">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm break-words">{children}</dd>
        </div>
    );
}

export default function BatchShow({ batch, jobs }: { batch: Batch; jobs: Paginated<Job> }) {
    const { t, locale } = useTranslation();
    useAutoRefresh(['batch', 'jobs']);

    return (
        <Layout
            title={batch.name || t('Batch')}
            badge={<StatusBadge status={batch.status === 'running' ? 'running' : BATCH_STATUS[batch.status]} />}
            actions={
                batch.retry_url && (
                    <ConfirmAction
                        url={batch.retry_url}
                        method="post"
                        icon={RotateCcw}
                        label={t('Retry failed jobs')}
                        title={t('Retry the failed jobs of this batch?')}
                        description={t(':count failed job(s) will be pushed back onto the queue.', { count: batch.failed })}
                        confirmLabel={t('Retry failed jobs')}
                    />
                )
            }
        >
            <Card>
                <dl>
                    <Row label="ID">
                        <span className="font-mono">{batch.id}</span>
                    </Row>
                    <Row label={t('Progress')}>
                        <span className="flex flex-wrap items-center gap-3">
                            <BatchProgress batch={batch} />
                            {t(':progress% · :processed / :total processed · :failed failed', {
                                progress: batch.progress,
                                processed: batch.processed,
                                total: batch.total,
                                failed: batch.failed,
                            })}
                        </span>
                    </Row>
                    <Row label={t('Created')}>{formatDateTime(batch.created_at, locale)}</Row>
                    {batch.finished_at && <Row label={t('Finished')}>{formatDateTime(batch.finished_at, locale)}</Row>}
                    {batch.cancelled_at && <Row label={t('Cancelled')}>{formatDateTime(batch.cancelled_at, locale)}</Row>}
                </dl>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>{t('Jobs')}</CardTitle>
                </CardHeader>
                <JobsTable jobs={jobs} />
            </Card>
        </Layout>
    );
}
