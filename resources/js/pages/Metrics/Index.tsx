import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Layout } from '@/components/layout';
import { Card, CardHeader } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatNumber, formatRuntime, formatSeconds } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Row {
    key: string;
    label: string;
    processed: number;
    failed: number;
    failure_rate: number;
    runtime: number | null;
    max_runtime: number | null;
    wait: number | null;
    url: string;
}

interface Props {
    type: 'jobs' | 'queues';
    minutes: number;
    periods: { value: number; label: string; url: string }[];
    tabs: Record<'jobs' | 'queues', string>;
    rows: Row[];
}

function Tab({ href, active, children }: { href: string; active: boolean; children: ReactNode }) {
    return (
        <Link
            href={href}
            preserveScroll
            className={cn(
                'rounded-md px-2.5 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground',
                active && 'bg-accent font-medium text-accent-foreground',
            )}
        >
            {children}
        </Link>
    );
}

export default function MetricsIndex({ type, minutes, periods, tabs, rows }: Props) {
    const { t, locale } = useTranslation();
    useAutoRefresh(['rows']);

    return (
        <Layout title={t('Metrics')}>
            <Card>
                <CardHeader>
                    <nav className="flex gap-1">
                        <Tab href={tabs.jobs} active={type === 'jobs'}>
                            {t('Jobs')}
                        </Tab>
                        <Tab href={tabs.queues} active={type === 'queues'}>
                            {t('Queues')}
                        </Tab>
                    </nav>
                    <nav className="flex flex-wrap gap-1">
                        {periods.map((period) => (
                            <Tab key={period.value} href={period.url} active={period.value === minutes}>
                                {t(period.label)}
                            </Tab>
                        ))}
                    </nav>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{type === 'jobs' ? t('Job') : t('Queue')}</TableHead>
                            <TableHead className="text-right">{t('Processed')}</TableHead>
                            <TableHead className="text-right">{t('Failed')}</TableHead>
                            <TableHead className="text-right">{t('Failure rate')}</TableHead>
                            <TableHead className="text-right">{t('Avg runtime')}</TableHead>
                            <TableHead className="text-right">{t('Max runtime')}</TableHead>
                            <TableHead className="text-right">{t('Avg wait')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                                    {t('No job processed during this period.')}
                                </TableCell>
                            </TableRow>
                        )}
                        {rows.map((row) => (
                            <TableRow key={row.key}>
                                <TableCell>
                                    <Link href={row.url} className="font-medium text-primary hover:underline" title={row.key}>
                                        {row.label}
                                    </Link>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{formatNumber(row.processed, locale)}</TableCell>
                                <TableCell className="text-right tabular-nums">{formatNumber(row.failed, locale)}</TableCell>
                                <TableCell className={cn('text-right tabular-nums', row.failure_rate > 0 && 'text-destructive')}>
                                    {formatNumber(row.failure_rate, locale)} %
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{formatRuntime(row.runtime, locale)}</TableCell>
                                <TableCell className="text-right tabular-nums">{formatRuntime(row.max_runtime, locale)}</TableCell>
                                <TableCell className="text-right tabular-nums">{formatSeconds(row.wait)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </Layout>
    );
}
