import { Link, router, usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { ConfirmAction } from '@/components/confirm-action';
import { Layout } from '@/components/layout';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime, formatNumber, formatRelative } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ErrorGroup, SharedProps } from '@/types';

interface Props {
    groups: ErrorGroup[];
    ready: boolean;
    queues: string[];
    filters: { queue: string };
}

const shortClass = (name: string) => name.split('\\').pop() ?? name;

/** The actions column stays visible when the table scrolls horizontally (small screens). */
const STICKY = 'sticky right-0 z-10 shadow-[-8px_0_8px_-8px_rgb(0_0_0/0.15)]';

export default function ErrorsIndex({ groups, ready, queues, filters }: Props) {
    const { t, locale } = useTranslation();
    const { nav } = usePage<SharedProps>().props;
    useAutoRefresh(['groups', 'queues']);

    const failed = groups.reduce((total, group) => total + group.count, 0);
    const retryable = groups.some((group) => group.retry_url);

    return (
        <Layout title={t('Errors')}>
            {!ready && (
                <div className="mb-6 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                    {t('Run the migrations of Meridian to group the failed jobs by error (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).')}
                </div>
            )}

            <Card>
                <CardHeader>
                    <div>
                        <CardTitle>{t('Failed jobs grouped by error')}</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(':count failed job(s), :groups distinct error(s)', {
                                count: formatNumber(failed, locale),
                                groups: formatNumber(groups.length, locale),
                            })}
                        </p>
                    </div>
                    <NativeSelect
                        aria-label={t('Filter by queue')}
                        value={filters.queue}
                        onChange={(event) => router.get(nav.errors, event.target.value ? { queue: event.target.value } : {}, { preserveState: true })}
                    >
                        <option value="">{t('All queues')}</option>
                        {queues.map((queue) => (
                            <option key={queue} value={queue}>
                                {queue}
                            </option>
                        ))}
                    </NativeSelect>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Error')}</TableHead>
                            <TableHead>{t('Jobs')}</TableHead>
                            <TableHead className="text-right">{t('Occurrences')}</TableHead>
                            <TableHead>{t('First seen')}</TableHead>
                            <TableHead>{t('Last seen')}</TableHead>
                            {retryable && <TableHead className={cn(STICKY, 'bg-muted text-right')}>{t('Actions')}</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {groups.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={retryable ? 6 : 5} className="py-10 text-center text-muted-foreground">
                                    {t('No failed job. Well done!')}
                                </TableCell>
                            </TableRow>
                        )}
                        {groups.map((group) => (
                            <TableRow key={group.fingerprint}>
                                <TableCell className="max-w-xl min-w-72 whitespace-normal">
                                    <Link href={group.url} className="font-medium text-primary hover:underline" title={group.class}>
                                        {shortClass(group.class)}
                                    </Link>
                                    <div className="mt-0.5 line-clamp-2 text-sm break-words" title={group.message}>
                                        {group.message || '—'}
                                    </div>
                                    {group.file && (
                                        <div className="mt-0.5 font-mono text-[11px] break-all text-muted-foreground">
                                            {group.file}:{group.line}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell className="whitespace-normal">
                                    <span className="flex flex-wrap gap-1">
                                        {group.jobs.slice(0, 3).map((job) => (
                                            <span key={job} className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground" title={job}>
                                                {shortClass(job)}
                                            </span>
                                        ))}
                                        {group.jobs.length > 3 && <span className="text-xs text-muted-foreground">+{group.jobs.length - 3}</span>}
                                    </span>
                                    <div className="mt-1 text-xs text-muted-foreground">{group.queues.join(', ')}</div>
                                </TableCell>
                                <TableCell className="text-right font-medium tabular-nums">
                                    <Link href={group.url} className="hover:underline">
                                        {formatNumber(group.count, locale)}
                                    </Link>
                                </TableCell>
                                <TableCell title={formatDateTime(group.first_seen, locale)}>{formatRelative(group.first_seen, locale)}</TableCell>
                                <TableCell title={formatDateTime(group.last_seen, locale)}>
                                    <Link href={group.sample_url} className="hover:underline">
                                        {formatRelative(group.last_seen, locale)}
                                    </Link>
                                </TableCell>
                                {retryable && (
                                    <TableCell className={cn(STICKY, 'bg-card text-right')}>
                                        {group.retry_url && (
                                            <ConfirmAction
                                                compact
                                                url={group.retry_url}
                                                method="post"
                                                data={{ error: group.fingerprint, ...(filters.queue ? { queue: filters.queue } : {}) }}
                                                icon={RotateCcw}
                                                label={t('Retry all')}
                                                title={t('Retry :count failed job(s)?', { count: formatNumber(group.count, locale) })}
                                                description={t('Every failed job with this error will be pushed back onto its queue.')}
                                                confirmLabel={t('Retry all')}
                                            />
                                        )}
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </Layout>
    );
}
