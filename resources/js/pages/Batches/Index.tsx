import { Link } from '@inertiajs/react';
import { Layout } from '@/components/layout';
import { StatusBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime, formatNumber, formatRelative } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { Batch } from '@/types';

export function BatchProgress({ batch }: { batch: Batch }) {
    const done = batch.total ? ((batch.processed - 0) / batch.total) * 100 : 0;
    const failed = batch.total ? (batch.failed / batch.total) * 100 : 0;

    return (
        <div className="flex h-1.5 w-32 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow={batch.progress} aria-valuemin={0} aria-valuemax={100}>
            <span className="h-full bg-success" style={{ width: `${done}%` }} />
            <span className="h-full bg-destructive" style={{ width: `${failed}%` }} />
        </div>
    );
}

export const BATCH_STATUS = { running: 'pending', finished: 'finished', failed: 'failed', cancelled: 'cancelled' } as const;

export default function BatchesIndex({ batches }: { batches: Batch[] }) {
    const { t, locale } = useTranslation();
    useAutoRefresh(['batches']);

    return (
        <Layout title={t('Batches')}>
            <Card>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Batch')}</TableHead>
                            <TableHead>{t('Progress')}</TableHead>
                            <TableHead className="text-end">{t('Jobs')}</TableHead>
                            <TableHead className="text-end">{t('Pending')}</TableHead>
                            <TableHead className="text-end">{t('Failed')}</TableHead>
                            <TableHead>{t('Status')}</TableHead>
                            <TableHead>{t('Created')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {batches.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                                    {t('No batches (or no job_batches table).')}
                                </TableCell>
                            </TableRow>
                        )}
                        {batches.map((batch) => (
                            <TableRow key={batch.id}>
                                <TableCell>
                                    <Link href={batch.url} className="font-medium text-primary hover:underline">
                                        {batch.name || batch.id}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <span className="flex items-center gap-2">
                                        <BatchProgress batch={batch} />
                                        <span className="text-xs text-muted-foreground tabular-nums">{batch.progress}%</span>
                                    </span>
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(batch.total, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(batch.pending, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(batch.failed, locale)}</TableCell>
                                <TableCell>
                                    <StatusBadge status={batch.status === 'running' ? 'running' : BATCH_STATUS[batch.status]} />
                                </TableCell>
                                <TableCell title={formatDateTime(batch.created_at, locale)}>{formatRelative(batch.created_at, locale)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </Layout>
    );
}
