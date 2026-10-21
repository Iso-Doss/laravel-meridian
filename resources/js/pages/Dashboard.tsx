import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AlertTriangle, Clock, Cpu, Gauge, Hourglass, Loader, Server, Snail, Timer, TrendingUp, Users } from 'lucide-react';
import { BarChart } from '@/components/bar-chart';
import { Layout } from '@/components/layout';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatBytes, formatCompact, formatNumber, formatRelative, formatRuntime, formatSeconds, formatTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { SharedProps, Supervisor, Worker, Workload } from '@/types';

interface Stats {
    status: 'running' | 'paused' | 'inactive';
    jobsPerMinute: number;
    recentJobs: number;
    failedJobs: number;
    pendingJobs: number;
    delayedJobs: number;
    processing: number;
    processes: number;
    maxWait: { queue: string; seconds: number } | null;
    maxRuntime: { queue: string; runtime: number } | null;
    maxThroughput: { queue: string; count: number } | null;
}

interface Props {
    stats: Stats;
    workload: Workload[];
    throughput: { time: number; count: number }[];
    supervisors: Supervisor[];
    workers: Worker[];
}

function Empty({ children }: { children: ReactNode }) {
    return (
        <TableRow>
            <TableCell colSpan={8} className="py-10 text-center whitespace-normal text-muted-foreground">
                {children}
            </TableCell>
        </TableRow>
    );
}

export default function Dashboard({ stats, workload, throughput, supervisors, workers }: Props) {
    const { t, locale } = useTranslation();
    const { nav } = usePage<SharedProps>().props;
    useAutoRefresh();

    const peak = Math.max(0, ...throughput.map((point) => point.count));

    return (
        <Layout title={t('Dashboard')} badge={<StatusBadge status={stats.status} />}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Gauge} label={t('Jobs per minute')} value={formatNumber(stats.jobsPerMinute, locale)} hint={t('average, last hour')} />
                <StatCard icon={Timer} label={t('Jobs past hour')} value={formatCompact(stats.recentJobs, locale)} hint={t('completed + failed')} />
                <StatCard
                    icon={AlertTriangle}
                    label={t('Failed jobs')}
                    value={formatCompact(stats.failedJobs, locale)}
                    hint={
                        <Link href={nav.jobs.failed} className="text-primary hover:underline">
                            {t('past 7 days')}
                        </Link>
                    }
                />
                <StatCard
                    icon={Clock}
                    label={t('Pending')}
                    value={formatCompact(stats.pendingJobs, locale)}
                    hint={t('+ :count delayed', { count: formatNumber(stats.delayedJobs, locale) })}
                />
                <StatCard
                    icon={Loader}
                    label={t('Processing')}
                    value={formatNumber(stats.processing, locale)}
                    hint={t(':count live worker(s)', { count: stats.processes })}
                />
                <StatCard
                    icon={Hourglass}
                    label={t('Max wait time')}
                    value={stats.maxWait ? formatSeconds(stats.maxWait.seconds) : '—'}
                    hint={stats.maxWait?.queue ?? t('no pending jobs')}
                />
                <StatCard
                    icon={Snail}
                    label={t('Max runtime')}
                    value={stats.maxRuntime ? formatRuntime(stats.maxRuntime.runtime, locale) : '—'}
                    hint={stats.maxRuntime?.queue ?? t('no job in the last hour')}
                />
                <StatCard
                    icon={TrendingUp}
                    label={t('Max throughput')}
                    value={stats.maxThroughput ? t(':count jobs', { count: formatNumber(stats.maxThroughput.count, locale) }) : '—'}
                    hint={stats.maxThroughput ? t(':queue, last hour', { queue: stats.maxThroughput.queue }) : t('no job in the last hour')}
                />
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>{t('Throughput')}</CardTitle>
                    <CardDescription>{t('jobs processed per minute, last 60 minutes')}</CardDescription>
                </CardHeader>
                <CardContent>
                    <BarChart
                        ariaLabel={t('Jobs per minute')}
                        bars={throughput.map((point) => ({
                            key: point.time,
                            value: point.count,
                            label: t(':time — :count job(s)', { time: formatTime(point.time, locale), count: point.count }),
                        }))}
                    />
                    {throughput.length > 0 && (
                        <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                            <span>{formatTime(throughput[0].time, locale)}</span>
                            <span>{t('peak :count/min', { count: peak })}</span>
                            <span>{formatTime(throughput[throughput.length - 1].time, locale)}</span>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>
                        <Gauge className="size-4 text-muted-foreground" /> {t('Current workload')}
                    </CardTitle>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Queue')}</TableHead>
                            <TableHead className="text-end">{t('Pending')}</TableHead>
                            <TableHead className="text-end">{t('Delayed')}</TableHead>
                            <TableHead className="text-end">{t('Reserved')}</TableHead>
                            <TableHead className="text-end">{t('Processes')}</TableHead>
                            <TableHead className="text-end">{t('Wait')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {workload.length === 0 && <Empty>{t('No database queue connection found in config/queue.php.')}</Empty>}
                        {workload.map((queue) => (
                            <TableRow key={`${queue.connection}:${queue.queue}`}>
                                <TableCell>
                                    <span className="text-muted-foreground">{queue.connection}:</span>
                                    <Link href={`${nav.jobs.pending}?queue=${encodeURIComponent(queue.queue)}`} className="text-primary hover:underline">
                                        {queue.queue}
                                    </Link>
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(queue.pending, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(queue.delayed, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(queue.reserved, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{queue.processes}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatSeconds(queue.wait)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>
                        <Server className="size-4 text-muted-foreground" /> {t('Supervisors')}
                    </CardTitle>
                    <code className="text-xs text-muted-foreground">php artisan meridian</code>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Supervisor')}</TableHead>
                            <TableHead>{t('Host')}</TableHead>
                            <TableHead>{t('Connection')}</TableHead>
                            <TableHead>{t('Queues')}</TableHead>
                            <TableHead className="text-end">{t('Processes')}</TableHead>
                            <TableHead>{t('Balancing')}</TableHead>
                            <TableHead>{t('Status')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {supervisors.length === 0 && <Empty>{t('No supervisor running. Workers started with queue:work still appear below.')}</Empty>}
                        {supervisors.map((supervisor) => (
                            <TableRow key={supervisor.name}>
                                <TableCell className="font-medium">{supervisor.short_name}</TableCell>
                                <TableCell>
                                    {supervisor.hostname} <span className="text-muted-foreground">#{supervisor.pid}</span>
                                </TableCell>
                                <TableCell>{supervisor.connection}</TableCell>
                                <TableCell>
                                    <span className="flex flex-wrap gap-1">
                                        {Object.entries(supervisor.processes).map(([queue, count]) => (
                                            <span key={queue} className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                {queue}: {count}
                                            </span>
                                        ))}
                                    </span>
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{supervisor.total_processes}</TableCell>
                                <TableCell>{supervisor.balance ?? t('off')}</TableCell>
                                <TableCell>
                                    <StatusBadge status={supervisor.status} />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>
                        <Users className="size-4 text-muted-foreground" /> {t('Workers')}
                    </CardTitle>
                    <CardDescription>{t(':count live', { count: workers.length })}</CardDescription>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Process')}</TableHead>
                            <TableHead>{t('Supervisor')}</TableHead>
                            <TableHead>{t('Queue')}</TableHead>
                            <TableHead>{t('Status')}</TableHead>
                            <TableHead className="text-end">{t('Jobs')}</TableHead>
                            <TableHead className="text-end">{t('Memory')}</TableHead>
                            <TableHead>{t('Started')}</TableHead>
                            <TableHead>{t('Last seen')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {workers.length === 0 && <Empty>{t('No worker is running.')}</Empty>}
                        {workers.map((worker) => (
                            <TableRow key={worker.name}>
                                <TableCell className="font-mono text-xs">
                                    <Cpu className="me-1.5 inline size-3.5 text-muted-foreground" />
                                    {worker.name}
                                </TableCell>
                                <TableCell>{worker.supervisor ?? '—'}</TableCell>
                                <TableCell>
                                    <span className="text-muted-foreground">{worker.connection}:</span>
                                    {worker.queue}
                                </TableCell>
                                <TableCell>
                                    <StatusBadge status={worker.status} />
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(worker.jobs_processed, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatBytes(worker.memory, locale)}</TableCell>
                                <TableCell>{formatRelative(worker.started_at, locale)}</TableCell>
                                <TableCell>{formatRelative(worker.last_seen_at, locale)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </Layout>
    );
}
