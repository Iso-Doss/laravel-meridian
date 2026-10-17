import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BarChart } from '@/components/bar-chart';
import { Layout } from '@/components/layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, formatRuntime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Snapshot {
    bucket: number;
    processed: number;
    failed: number;
    runtime: number;
    wait: number;
}

export default function MetricsShow({ name, back_url, snapshots }: { type: string; name: string; back_url: string; snapshots: Snapshot[] }) {
    const { t, locale } = useTranslation();

    const charts = [
        { field: 'processed' as const, title: t('Throughput'), description: t('jobs per snapshot'), format: (value: number) => String(value) },
        { field: 'runtime' as const, title: t('Average runtime'), description: t('milliseconds'), format: (value: number) => formatRuntime(value, locale) },
    ];

    return (
        <Layout
            title={name}
            actions={
                <Button variant="outline" size="sm" asChild>
                    <Link href={back_url}>
                        <ArrowLeft /> {t('Metrics')}
                    </Link>
                </Button>
            }
        >
            {snapshots.length === 0 ? (
                <Card>
                    <CardContent className="py-10 text-center text-sm text-muted-foreground">
                        {t('No snapshot yet. Snapshots are stored by php artisan meridian:snapshot (scheduled every 5 minutes).')}
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-6">
                    {charts.map((chart) => {
                        const peak = Math.max(0, ...snapshots.map((snapshot) => snapshot[chart.field]));

                        return (
                            <Card key={chart.field}>
                                <CardHeader>
                                    <div>
                                        <CardTitle>{chart.title}</CardTitle>
                                        <CardDescription>{chart.description}</CardDescription>
                                    </div>
                                    <span className="text-xs text-muted-foreground">{t('peak :value', { value: chart.format(peak) })}</span>
                                </CardHeader>
                                <CardContent>
                                    <BarChart
                                        ariaLabel={chart.title}
                                        bars={snapshots.map((snapshot) => ({
                                            key: snapshot.bucket,
                                            value: snapshot[chart.field],
                                            label: `${formatDateTime(snapshot.bucket, locale)} — ${chart.format(snapshot[chart.field])}`,
                                        }))}
                                    />
                                    <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                                        <span>{formatDateTime(snapshots[0].bucket, locale)}</span>
                                        <span>{formatDateTime(snapshots[snapshots.length - 1].bucket, locale)}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}
        </Layout>
    );
}
