import { Link } from '@inertiajs/react';
import { Layout } from '@/components/layout';
import { RecentRuns } from '@/components/schedule-runs';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime, formatRelative, formatRuntime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { ScheduledTask } from '@/types';

export function TaskFlags({ task }: { task: ScheduledTask }) {
    const { t } = useTranslation();
    const flags = [
        task.repeat_seconds ? t('every :seconds s', { seconds: task.repeat_seconds }) : null,
        task.background ? t('in background') : null,
        task.without_overlapping ? t('without overlapping') : null,
        task.on_one_server ? t('on one server') : null,
        task.timezone,
    ].filter(Boolean);

    return (
        <span className="flex flex-wrap items-center gap-1">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">{task.expression}</code>
            {flags.map((flag) => (
                <span key={flag} className="text-xs text-muted-foreground">
                    · {flag}
                </span>
            ))}
        </span>
    );
}

export default function ScheduleIndex({ tasks, ready }: { tasks: ScheduledTask[]; ready: boolean }) {
    const { t, locale } = useTranslation();
    useAutoRefresh(['tasks']);

    const failing = tasks.filter((task) => task.health === 'failed' || task.health === 'missed').length;

    return (
        <Layout title={t('Schedule')}>
            {!ready && (
                <div className="mb-6 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                    {t('Run the migrations of Meridian to monitor the scheduled tasks (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).')}
                </div>
            )}

            <Card>
                <CardHeader>
                    <div>
                        <CardTitle>{t('Scheduled tasks')}</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(':count task(s), :failing failing or missed', { count: tasks.length, failing })}
                        </p>
                    </div>
                    <code className="text-xs text-muted-foreground">php artisan schedule:run</code>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Task')}</TableHead>
                            <TableHead>{t('Status')}</TableHead>
                            <TableHead>{t('Last run')}</TableHead>
                            <TableHead className="text-right">{t('Duration')}</TableHead>
                            <TableHead>{t('Recent runs')}</TableHead>
                            <TableHead>{t('Next run')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {tasks.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={6} className="py-10 text-center whitespace-normal text-muted-foreground">
                                    {t('No scheduled task known yet. They appear once schedule:run or meridian:snapshot has run.')}
                                </TableCell>
                            </TableRow>
                        )}
                        {tasks.map((task) => (
                            <TableRow key={task.id}>
                                <TableCell className="max-w-md min-w-64 whitespace-normal">
                                    <Link href={task.url ?? '#'} className="font-medium break-words text-primary hover:underline">
                                        {task.name}
                                    </Link>
                                    <div className="mt-1">
                                        <TaskFlags task={task} />
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <StatusBadge status={task.health} />
                                    {task.missed_at && (
                                        <div className="mt-1 text-xs text-destructive">{t('due :time', { time: formatRelative(task.missed_at, locale) })}</div>
                                    )}
                                </TableCell>
                                <TableCell title={task.last_run ? formatDateTime(task.last_run.started_at, locale) : undefined}>
                                    {task.last_run ? formatRelative(task.last_run.started_at, locale) : '—'}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{formatRuntime(task.last_run?.runtime ?? null, locale)}</TableCell>
                                <TableCell>
                                    <RecentRuns runs={task.recent} />
                                </TableCell>
                                <TableCell title={task.next_run_at ? formatDateTime(task.next_run_at, locale) : undefined}>
                                    {formatRelative(task.next_run_at, locale)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </Layout>
    );
}
