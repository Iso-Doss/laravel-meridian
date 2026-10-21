import { Link, useForm } from '@inertiajs/react';
import { BookmarkPlus, Loader2, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import { Layout } from '@/components/layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { formatNumber } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface MonitoredTag {
    tag: string;
    jobs: number;
    failed: number;
    pending: number;
    url: string;
}

interface Props {
    tags: MonitoredTag[];
    ready: boolean;
    /** Null for read-only users. */
    store_url: string | null;
    destroy_url: string | null;
}

export default function MonitoringIndex({ tags, ready, store_url, destroy_url }: Props) {
    const { t, locale } = useTranslation();
    const form = useForm({ tag: '' });
    useAutoRefresh(['tags']);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store_url ?? '', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <Layout title={t('Monitoring')}>
            {!ready && (
                <div className="mb-6 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                    {t('Run the migrations of Meridian to monitor tags (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).')}
                </div>
            )}

            {store_url && (
                <Card className="mb-6">
                    <CardHeader>
                        <div>
                            <CardTitle>{t('Monitor a tag')}</CardTitle>
                            <CardDescription>
                                {t('The jobs carrying a monitored tag are listed here and kept longer (trim.monitored).')}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-wrap gap-2">
                            <Input
                                value={form.data.tag}
                                onChange={(event) => form.setData('tag', event.target.value)}
                                placeholder="App\Models\User:42"
                                aria-label={t('Tag to monitor')}
                                aria-invalid={!!form.errors.tag}
                                className="max-w-md font-mono"
                                disabled={!ready}
                            />
                            <Button type="submit" disabled={!ready || form.processing || form.data.tag.trim() === ''}>
                                {form.processing ? <Loader2 className="animate-spin" /> : <BookmarkPlus />}
                                {t('Monitor')}
                            </Button>
                        </form>
                        {form.errors.tag && <p className="mt-2 text-sm text-destructive">{form.errors.tag}</p>}
                    </CardContent>
                </Card>
            )}

            <Card>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Tag')}</TableHead>
                            <TableHead className="text-end">{t('Jobs')}</TableHead>
                            <TableHead className="text-end">{t('Pending')}</TableHead>
                            <TableHead className="text-end">{t('Failed')}</TableHead>
                            {destroy_url && <TableHead className="text-end">{t('Actions')}</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {tags.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={destroy_url ? 5 : 4} className="py-10 text-center text-muted-foreground">
                                    {t('No tag is monitored.')}
                                </TableCell>
                            </TableRow>
                        )}
                        {tags.map((tag) => (
                            <TableRow key={tag.tag}>
                                <TableCell>
                                    <Link href={tag.url} className="font-mono text-primary hover:underline">
                                        {tag.tag}
                                    </Link>
                                </TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(tag.jobs, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(tag.pending, locale)}</TableCell>
                                <TableCell className="text-end tabular-nums">{formatNumber(tag.failed, locale)}</TableCell>
                                {destroy_url && (
                                    <TableCell className="text-end">
                                        <ConfirmAction
                                            compact
                                            url={destroy_url}
                                            method="delete"
                                            data={{ tag: tag.tag }}
                                            tone="destructive"
                                            icon={Trash2}
                                            label={t('Stop monitoring')}
                                            title={t('Stop monitoring :tag?', { tag: tag.tag })}
                                            description={t('Its jobs will be pruned like the other ones.')}
                                            confirmLabel={t('Stop monitoring')}
                                        />
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
