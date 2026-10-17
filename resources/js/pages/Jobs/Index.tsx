import { Link, router, usePage } from '@inertiajs/react';
import { RotateCcw, Search, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import { JobsTable } from '@/components/jobs-table';
import { Layout } from '@/components/layout';
import { Card, CardHeader } from '@/components/ui/card';
import { Input, NativeSelect } from '@/components/ui/input';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatNumber } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Job, JobStatus, Paginated, SharedProps } from '@/types';

interface Props {
    status: JobStatus | null;
    filters: { search: string; queue: string; tag: string };
    jobs: Paginated<Job>;
    queues: string[];
    counts: Record<JobStatus, number>;
    retry_all_url: string;
}

const TITLES: Record<'all' | JobStatus, string> = {
    all: 'All jobs',
    pending: 'Pending jobs',
    processing: 'Processing jobs',
    completed: 'Completed jobs',
    failed: 'Failed jobs',
    silenced: 'Silenced jobs',
};

const TABS: ('all' | JobStatus)[] = ['all', 'pending', 'processing', 'completed', 'failed', 'silenced'];
const TAB_LABELS: Record<'all' | JobStatus, string> = {
    all: 'All',
    pending: 'Pending',
    processing: 'Processing',
    completed: 'Completed',
    failed: 'Failed',
    silenced: 'Silenced',
};

export default function JobsIndex({ status, filters, jobs, queues, counts, retry_all_url }: Props) {
    const { t, locale } = useTranslation();
    const { nav } = usePage<SharedProps>().props;
    const [search, setSearch] = useState(filters.search);
    useAutoRefresh(['jobs', 'counts', 'queues']);

    const current = status ?? 'all';
    const query = (overrides: Partial<Props['filters']>) =>
        Object.fromEntries(Object.entries({ ...filters, ...overrides }).filter(([, value]) => value !== ''));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(nav.jobs[current], query({ search }), { preserveState: true, preserveScroll: true });
    };

    return (
        <Layout
            title={t(TITLES[current])}
            actions={
                current === 'failed' &&
                jobs.meta.total > 0 && (
                    <ConfirmAction
                        url={retry_all_url}
                        method="post"
                        data={query({})}
                        icon={RotateCcw}
                        label={t('Retry all')}
                        title={t('Retry :count failed job(s)?', { count: formatNumber(jobs.meta.total, locale) })}
                        description={
                            filters.queue || filters.search || filters.tag
                                ? t('Every failed job matching the current filters will be pushed back onto its queue.')
                                : t('Every failed job will be pushed back onto its queue.')
                        }
                        confirmLabel={t('Retry all')}
                    />
                )
            }
        >
            <Card>
                <CardHeader>
                    <nav className="flex flex-wrap gap-1">
                        {TABS.map((tab) => (
                            <Link
                                key={tab}
                                href={nav.jobs[tab]}
                                data={query({})}
                                preserveScroll
                                className={cn(
                                    'rounded-md px-2.5 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground',
                                    current === tab && 'bg-accent font-medium text-accent-foreground',
                                )}
                            >
                                {t(TAB_LABELS[tab])}
                                {tab !== 'all' && <span className="ml-1.5 text-xs opacity-70 tabular-nums">{formatNumber(counts[tab], locale)}</span>}
                            </Link>
                        ))}
                    </nav>
                    <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
                        {filters.tag && (
                            <Link
                                href={nav.jobs[current]}
                                data={query({ tag: '' })}
                                className="inline-flex items-center gap-1 rounded-md border bg-accent px-2 py-1 font-mono text-xs text-accent-foreground"
                                title={t('Remove the tag filter')}
                            >
                                {filters.tag}
                                <X className="size-3" />
                            </Link>
                        )}
                        <NativeSelect
                            aria-label={t('Filter by queue')}
                            value={filters.queue}
                            onChange={(event) => router.get(nav.jobs[current], query({ queue: event.target.value }), { preserveState: true })}
                        >
                            <option value="">{t('All queues')}</option>
                            {queues.map((queue) => (
                                <option key={queue} value={queue}>
                                    {queue}
                                </option>
                            ))}
                        </NativeSelect>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={t('Job, tag or UUID…')}
                                aria-label={t('Search')}
                                className="w-60 pl-8"
                            />
                        </div>
                    </form>
                </CardHeader>
                <JobsTable jobs={jobs} />
            </Card>
        </Layout>
    );
}
