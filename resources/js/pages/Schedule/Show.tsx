import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Layout } from '@/components/layout';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime, formatRelative, formatRuntime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { TaskFlags } from '@/pages/Schedule/Index';
import type { Paginated, ScheduledTask, ScheduleRun } from '@/types';

export default function ScheduleShow({ task, runs, back_url }: { task: ScheduledTask; runs: Paginated<ScheduleRun>; back_url: string }) {
    const { t, locale } = useTranslation();
    useAutoRefresh(['task', 'runs']);

    return (
        <Layout
            title={task.name}
            badge={<StatusBadge status={task.health} />}
            actions={
                <Button variant="outline" asChild>
                    <Link href={back_url}>
                        <ArrowLeft className="rtl:-scale-x-100" /> {t('Schedule')}
                    </Link>
                </Button>
            }
        >
            <Card>
                <CardHeader>
                    <TaskFlags task={task} />
                    <span className="text-sm text-muted-foreground">
                        {t('Next run')}: {formatDateTime(task.next_run_at, locale)}
                    </span>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Started at')}</TableHead>
                            <TableHead>{t('Status')}</TableHead>
                            <TableHead className="text-end">{t('Duration')}</TableHead>
                            <TableHead className="text-end">{t('Exit code')}</TableHead>
                            <TableHead>{t('Host')}</TableHead>
                            <TableHead>{t('Exception')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {runs.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                                    {t('This task has not run yet.')}
                                </TableCell>
                            </TableRow>
                        )}
                        {runs.data.map((run, index) => (
                            <TableRow key={`${run.started_at}-${index}`}>
                                <TableCell title={formatRelative(run.started_at, locale)}>{formatDateTime(run.started_at, locale)}</TableCell>
                                <TableCell>
                                    <StatusBadge status={run.status} />
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{formatRuntime(run.runtime, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{run.exit_code ?? '—'}</TableCell>
                                <TableCell>{run.host ?? '—'}</TableCell>
                                <TableCell className="max-w-md truncate font-mono text-xs" title={run.exception ?? undefined}>
                                    {run.exception ? run.exception.split('\n')[0] : '—'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                <Pagination paginator={runs} />
            </Card>
        </Layout>
    );
}
