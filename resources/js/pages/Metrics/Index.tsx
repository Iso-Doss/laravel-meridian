import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Layout } from '@/components/layout';
import { HourlyHeatmap, type HourlyPoint } from '@/components/hourly-heatmap';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    p95: number | null;
    p99: number | null;
    previous: { processed: number; failure_rate: number; runtime: number | null } | null;
    url: string;
}

interface Props {
    type: 'jobs' | 'queues';
    minutes: number;
    periods: { value: number; label: string; url: string }[];
    tabs: Record<'jobs' | 'queues', string>;
    rows: Row[];
    hourly: HourlyPoint[];
}

/** Change against the previous period: in percent, or in points for rates. */
function Trend({ current, previous, worseWhenUp = false, points = false }: { current: number | null; previous: number | null | undefined; worseWhenUp?: boolean; points?: boolean }) {
    const { t, locale } = useTranslation();

    if (current === null || previous === null || previous === undefined || (!points && previous === 0)) {
        return null;
    }

    const delta = points ? current - previous : ((current - previous) / previous) * 100;

    if (Math.abs(delta) < 0.5) {
        return null;
    }

    const up = delta > 0;
    const text = `${up ? '▲' : '▼'} ${new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(Math.abs(delta))}${points ? ' pt' : ' %'}`;

    return (
        <div className={cn('text-[11px]', worseWhenUp ? (up ? 'text-destructive' : 'text-success') : 'text-muted-foreground')} title={t('compared with the previous period')}>
            {text}
        </div>
    );
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

export default function MetricsIndex({ type, minutes, periods, tabs, rows, hourly }: Props) {
    const { t, locale } = useTranslation();
    useAutoRefresh(['rows', 'hourly']);

    return (
        <Layout title={t('Metrics')}>
            <Card className="mb-6">
                <CardHeader>
                    <div>
                        <CardTitle>{t('Jobs per hour')}</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">{t('last 7 days, in your timezone; hours where 10% or more of the jobs failed are outlined')}</p>
                    </div>
                </CardHeader>
                <CardContent>
                    <HourlyHeatmap points={hourly} />
                </CardContent>
            </Card>

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
                            <TableHead className="text-end">{t('Processed')}</TableHead>
                            <TableHead className="text-end">{t('Failed')}</TableHead>
                            <TableHead className="text-end">{t('Failure rate')}</TableHead>
                            <TableHead className="text-end">{t('Avg runtime')}</TableHead>
                            <TableHead className="text-end" title={t('95% of the jobs ran faster')}>p95</TableHead>
                            <TableHead className="text-end" title={t('99% of the jobs ran faster')}>p99</TableHead>
                            <TableHead className="text-end">{t('Max runtime')}</TableHead>
                            <TableHead className="text-end">{t('Avg wait')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={9} className="py-10 text-center text-muted-foreground">
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
                                <TableCell className="text-end tabular-nums">
                                    {formatNumber(row.processed, locale)}
                                    <Trend current={row.processed} previous={row.previous?.processed} />
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(row.failed, locale)}</TableCell>
                                <TableCell className={cn('text-end tabular-nums', row.failure_rate > 0 && 'text-destructive')}>
                                    {formatNumber(row.failure_rate, locale)} %
                                    <Trend current={row.failure_rate} previous={row.previous?.failure_rate} worseWhenUp points />
                                </TableCell>
                                <TableCell className="text-end tabular-nums">
                                    {formatRuntime(row.runtime, locale)}
                                    <Trend current={row.runtime} previous={row.previous?.runtime} worseWhenUp />
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{formatRuntime(row.p95, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatRuntime(row.p99, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatRuntime(row.max_runtime, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatSeconds(row.wait)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </Layout>
    );
}
