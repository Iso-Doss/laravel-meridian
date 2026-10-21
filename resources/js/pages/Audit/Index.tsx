import { router, usePage } from '@inertiajs/react';
import { Layout } from '@/components/layout';
import { Pagination } from '@/components/pagination';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatDateTime, formatRelative } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { AuditEntry, Paginated, SharedProps } from '@/types';

interface Props {
    entries: Paginated<AuditEntry> | null;
    ready: boolean;
    filters: { action: string };
    actions: { value: string; label: string }[];
}

export default function AuditIndex({ entries, ready, filters, actions }: Props) {
    const { t, locale } = useTranslation();
    const { nav } = usePage<SharedProps>().props;
    useAutoRefresh(['entries']);

    return (
        <Layout title={t('Audit log')}>
            {!ready && (
                <div className="mb-6 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                    {t('Run the migrations of Meridian to record the audit log (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).')}
                </div>
            )}

            <Card>
                <CardHeader>
                    <div>
                        <CardTitle>{t('Who did what, and when')}</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">{t('Actions taken from the dashboard and the Artisan commands of Meridian.')}</p>
                    </div>
                    <NativeSelect
                        aria-label={t('Filter by action')}
                        value={filters.action}
                        onChange={(event) => router.get(nav.audit, event.target.value ? { action: event.target.value } : {}, { preserveState: true })}
                    >
                        <option value="">{t('All actions')}</option>
                        {actions.map((action) => (
                            <option key={action.value} value={action.value}>
                                {t(action.label)}
                            </option>
                        ))}
                    </NativeSelect>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Date')}</TableHead>
                            <TableHead>{t('User')}</TableHead>
                            <TableHead>{t('Action')}</TableHead>
                            <TableHead>{t('Subject')}</TableHead>
                            <TableHead>{t('Details')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {(entries?.data.length ?? 0) === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                                    {t('No action recorded yet.')}
                                </TableCell>
                            </TableRow>
                        )}
                        {entries?.data.map((entry, index) => (
                            <TableRow key={`${entry.created_at}-${index}`}>
                                <TableCell title={formatDateTime(entry.created_at, locale)}>{formatRelative(entry.created_at, locale)}</TableCell>
                                <TableCell>
                                    {entry.user === 'console' ? (
                                        <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">{t('Console')}</span>
                                    ) : (
                                        (entry.user ?? <span className="text-muted-foreground">{t('Guest')}</span>)
                                    )}
                                    {entry.ip && <div className="text-xs text-muted-foreground">{entry.ip}</div>}
                                </TableCell>
                                <TableCell className="font-medium">{t(entry.label)}</TableCell>
                                <TableCell className="max-w-64 truncate font-mono text-xs" title={entry.subject ?? undefined}>
                                    {entry.subject ?? '—'}
                                </TableCell>
                                <TableCell className="whitespace-normal">
                                    <span className="flex flex-wrap gap-1">
                                        {Object.entries(entry.details).map(([key, value]) => (
                                            <span key={key} className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                                                {key}: {String(value)}
                                            </span>
                                        ))}
                                    </span>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {entries && <Pagination paginator={entries} />}
            </Card>
        </Layout>
    );
}
